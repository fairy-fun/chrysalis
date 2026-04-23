<?php
declare(strict_types=1);

function repo_respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

function repo_fail(int $statusCode, string $error, array $extra = []): never
{
    repo_respond($statusCode, array_merge([
        'status' => 'error',
        'error' => $error,
    ], $extra));
}

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';


function normalize_visibility_list(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_string($item)) {
            continue;
        }

        $value = normalize_relative_path($item);
        if ($value === '') {
            continue;
        }

        $normalized[$value] = true;
    }

    return array_keys($normalized);
}

function normalize_relative_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));

    while (strpos($path, '//') !== false) {
        $path = str_replace('//', '/', $path);
    }

    $path = trim($path, '/');

    if ($path === '' || $path === '.') {
        return '';
    }

    $parts = explode('/', $path);
    $clean = [];

    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        $clean[] = $part;
    }

    return implode('/', $clean);
}

function reject_dangerous_input(string $path): void
{
    if (strpos($path, "\0") !== false) {
        repo_fail(403, 'Invalid path');
    }

    $rawParts = explode('/', str_replace('\\', '/', $path));
    foreach ($rawParts as $part) {
        if ($part === '..') {
            repo_fail(403, 'Path traversal is not allowed');
        }
    }
}

function build_absolute_path(string $repoRoot, string $relativePath): string
{
    if ($relativePath === '') {
        return $repoRoot;
    }

    return $repoRoot . '/' . $relativePath;
}

function ensure_within_repo_root(string $repoRoot, string $resolvedPath): void
{
    if ($resolvedPath !== $repoRoot && strpos($resolvedPath, $repoRoot . '/') !== 0) {
        repo_fail(403, 'Resolved path is outside repo root');
    }
}

/**
 * A requested directory is listable if it is:
 * - the repo root
 * - exactly a declared visible prefix
 * - inside a declared visible prefix
 * - an ancestor of any visible prefix or visible file
 *
 * Visible files themselves are not listable as directories,
 * but their ancestor directories are.
 */

function is_ancestor_of_visible_path(string $relativePath, array $visiblePrefixes, array $visibleFiles): bool
{
    $needle = $relativePath === '' ? '' : $relativePath . '/';

    foreach ($visiblePrefixes as $prefix) {
        if ($relativePath !== '' && strpos($prefix, $needle) === 0) {
            return true;
        }
    }

    foreach ($visibleFiles as $file) {
        if ($relativePath !== '' && strpos($file, $needle) === 0) {
            return true;
        }
    }

    return false;
}

function has_direct_visible_file_child(string $relativePath, array $visibleFiles): bool
{
    $needle = $relativePath === '' ? '' : $relativePath . '/';

    foreach ($visibleFiles as $file) {
        if ($relativePath === '') {
            if (strpos($file, '/') === false) {
                return true;
            }
            continue;
        }

        if (strpos($file, $needle) !== 0) {
            continue;
        }

        $remainder = substr($file, strlen($needle));
        if ($remainder !== '' && strpos($remainder, '/') === false) {
            return true;
        }
    }

    return false;
}

/**
 * A requested directory is listable if it is:
 * - the repo root
 * - exactly a declared visible prefix
 * - inside a declared visible prefix
 * - an ancestor of visible content, but only when it is acting as a bridge
 *   directory rather than a direct container of explicitly visible files
 */
function is_listable_path(string $relativePath, array $visiblePrefixes, array $visibleFiles): bool
{
    if ($relativePath === '') {
        return true;
    }

    foreach ($visiblePrefixes as $prefix) {
        if ($relativePath === $prefix) {
            return true;
        }

        if (strpos($relativePath, $prefix . '/') === 0) {
            return true;
        }
    }

    if (
        is_ancestor_of_visible_path($relativePath, $visiblePrefixes, $visibleFiles) &&
        !has_direct_visible_file_child($relativePath, $visibleFiles)
    ) {
        return true;
    }

    return false;
}


/**
 * Include child entries when:
 * - child is directly visible as a visible prefix
 * - child is inside a visible prefix
 * - child is an explicitly visible file
 * - child is an ancestor of any visible prefix or visible file
 */
function should_include_child(string $childRelativePath, array $visiblePrefixes, array $visibleFiles): bool
{
    foreach ($visiblePrefixes as $prefix) {
        if ($childRelativePath === $prefix) {
            return true;
        }

        if (strpos($childRelativePath, $prefix . '/') === 0) {
            return true;
        }
    }

    if (in_array($childRelativePath, $visibleFiles, true)) {
        return true;
    }

    $needle = $childRelativePath . '/';

    foreach ($visiblePrefixes as $prefix) {
        if (strpos($prefix, $needle) === 0) {
            return true;
        }
    }

    foreach ($visibleFiles as $file) {
        if (strpos($file, $needle) === 0) {
            return true;
        }
    }

    return false;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repo_fail(405, 'Method not allowed', ['allowed_method' => 'POST']);
}

function get_repo_runtime_config(): array
{
    $config = getConfig();

    $repoRootConfig = $config['chrysalis_repo_root'] ?? null;
    $visiblePrefixes = $config['chrysalis_repo_visible_prefixes'] ?? [];
    $visibleFiles = $config['chrysalis_repo_visible_files'] ?? [];

    if (!is_string($repoRootConfig) || $repoRootConfig === '') {
        repo_fail(500, 'Missing chrysalis_repo_root');
    }

    if (!is_array($visiblePrefixes)) {
        repo_fail(500, 'Invalid chrysalis_repo_visible_prefixes');
    }

    if (!is_array($visibleFiles)) {
        repo_fail(500, 'Invalid chrysalis_repo_visible_files');
    }

    $repoRootReal = realpath($repoRootConfig);
    if ($repoRootReal === false || !is_dir($repoRootReal)) {
        repo_fail(500, 'Invalid chrysalis_repo_root');
    }

    return [
        'repo_root' => rtrim(str_replace('\\', '/', $repoRootReal), '/'),
        'visible_prefixes' => normalize_visibility_list($visiblePrefixes),
        'visible_files' => normalize_visibility_list($visibleFiles),
    ];
}

requireAuth();
$config = get_repo_runtime_config();
$body = getJsonBody();

$inputPath = $body['path'] ?? '';

if (!is_string($inputPath)) {
    repo_fail(400, 'Missing or invalid path');
}

reject_dangerous_input($inputPath);

$relativePath = normalize_relative_path($inputPath);

if (
    $relativePath !== '' &&
    !is_listable_path($relativePath, $config['visible_prefixes'], $config['visible_files'])
) {
    repo_fail(403, 'Path is not visible', ['path' => $relativePath]);
}

$absolutePath = build_absolute_path($config['repo_root'], $relativePath);
$resolvedPath = realpath($absolutePath);

if ($resolvedPath === false || !is_dir($resolvedPath)) {
    repo_fail(404, 'Directory not found', ['path' => $relativePath]);
}

$resolvedPath = str_replace('\\', '/', $resolvedPath);
ensure_within_repo_root($config['repo_root'], $resolvedPath);

$items = scandir($resolvedPath);
if ($items === false) {
    repo_fail(500, 'Unable to read directory', ['path' => $relativePath]);
}

$entries = [];

foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }

    $childRelativePath = $relativePath === '' ? $item : $relativePath . '/' . $item;

    if (!should_include_child($childRelativePath, $config['visible_prefixes'], $config['visible_files'])) {
        continue;
    }

    $childAbsolutePath = $config['repo_root'] . '/' . $childRelativePath;
    $childResolvedPath = realpath($childAbsolutePath);

    if ($childResolvedPath === false) {
        continue;
    }

    $childResolvedPath = str_replace('\\', '/', $childResolvedPath);

    if (
        $childResolvedPath !== $config['repo_root'] &&
        strpos($childResolvedPath, $config['repo_root'] . '/') !== 0
    ) {
        continue;
    }

    $type = is_dir($childResolvedPath) ? 'dir' : (is_file($childResolvedPath) ? 'file' : null);
    if ($type === null) {
        continue;
    }

    $entries[] = [
        'name' => $item,
        'path' => $childRelativePath,
        'type' => $type,
    ];
}

usort($entries, static function (array $a, array $b): int {
    if ($a['type'] !== $b['type']) {
        return $a['type'] === 'dir' ? -1 : 1;
    }

    return strcmp($a['name'], $b['name']);
});

repo_respond(200, [
    'status' => 'ok',
    'path' => $relativePath,
    'entries' => $entries,
]);