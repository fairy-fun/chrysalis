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

function is_visible_path(string $relativePath, array $visiblePrefixes, array $visibleFiles): bool
{
    foreach ($visibleFiles as $file) {
        if ($relativePath === $file) {
            return true;
        }
    }

    foreach ($visiblePrefixes as $prefix) {
        if ($relativePath === $prefix || strpos($relativePath, $prefix . '/') === 0) {
            return true;
        }
    }

    return false;
}

function guess_mime_type(string $path): string
{
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($path);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }

    return 'application/octet-stream';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repo_fail(405, 'Method not allowed', ['allowed_method' => 'POST']);
}



requireAuth();

$config = get_repo_runtime_config();
$body = getJsonBody();

$inputPath = $body['path'] ?? null;

if (!is_string($inputPath)) {
    repo_fail(400, 'Missing or invalid path');
}

reject_dangerous_input($inputPath);

$relativePath = normalize_relative_path($inputPath);
if ($relativePath === '') {
    repo_fail(400, 'Path must not be empty');
}

$absolutePath = build_absolute_path($config['repo_root'], $relativePath);
$resolvedPath = realpath($absolutePath);

if ($resolvedPath === false || !is_file($resolvedPath)) {
    repo_fail(404, 'File not found', ['path' => $relativePath]);
}

$resolvedPath = str_replace('\\', '/', $resolvedPath);
ensure_within_repo_root($config['repo_root'], $resolvedPath);

if (!is_visible_path($relativePath, $config['visible_prefixes'], $config['visible_files'])) {
    repo_fail(403, 'Path is not visible', ['path' => $relativePath]);
}

$contents = file_get_contents($resolvedPath);
if ($contents === false) {
    repo_fail(500, 'Unable to read file', ['path' => $relativePath]);
}

repo_respond(200, [
    'status' => 'ok',
    'path' => $relativePath,
    'mime_type' => guess_mime_type($resolvedPath),
    'size_bytes' => filesize($resolvedPath),
    'contents' => $contents,
]);