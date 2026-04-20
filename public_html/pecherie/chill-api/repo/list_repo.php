<?php
declare(strict_types=1);

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

function fail(int $statusCode, string $error, array $extra = []): void
{
    respond($statusCode, array_merge([
        'status' => 'error',
        'error' => $error,
    ], $extra));
}

function load_repo_config(): array
{
    $config = require dirname(__DIR__, 4) . '/pecherie_config.php';

    if (!is_array($config)) {
        fail(500, 'Invalid configuration');
    }

    $apiKey = $config['pecherie_api_key'] ?? null;
    $repoRoot = $config['chrysalis_repo_root'] ?? null;
    $visiblePrefixes = $config['chrysalis_repo_visible_prefixes'] ?? [];
    $visibleFiles = $config['chrysalis_repo_visible_files'] ?? [];

    if (!is_string($apiKey) || $apiKey === '') {
        fail(500, 'Missing pecherie_api_key');
    }

    if (!is_string($repoRoot) || $repoRoot === '') {
        fail(500, 'Missing chrysalis_repo_root');
    }

    if (!is_array($visiblePrefixes)) {
        fail(500, 'Invalid chrysalis_repo_visible_prefixes');
    }

    if (!is_array($visibleFiles)) {
        fail(500, 'Invalid chrysalis_repo_visible_files');
    }

    $repoRootReal = realpath($repoRoot);
    if ($repoRootReal === false || !is_dir($repoRootReal)) {
        fail(500, 'Invalid chrysalis_repo_root');
    }

    return [
        'api_key' => $apiKey,
        'repo_root' => rtrim(str_replace('\\', '/', $repoRootReal), '/'),
        'visible_prefixes' => normalize_visibility_list($visiblePrefixes),
        'visible_files' => normalize_visibility_list($visibleFiles),
    ];
}

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

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        fail(405, 'Method not allowed', ['allowed_method' => 'POST']);
    }
}

function require_api_key(string $expectedApiKey): void
{
    $provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!is_string($provided) || $provided === '' || !hash_equals($expectedApiKey, $provided)) {
        fail(401, 'Unauthorized');
    }
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false) {
        fail(400, 'Unable to read request body');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fail(400, 'Invalid JSON body');
    }

    return $data;
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
        fail(403, 'Invalid path');
    }

    $rawParts = explode('/', str_replace('\\', '/', $path));
    foreach ($rawParts as $part) {
        if ($part === '..') {
            fail(403, 'Path traversal is not allowed');
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
        fail(403, 'Resolved path is outside repo root');
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

function should_include_child(string $childRelativePath, array $visiblePrefixes, array $visibleFiles): bool
{
    if (is_visible_path($childRelativePath, $visiblePrefixes, $visibleFiles)) {
        return true;
    }

    foreach ($visiblePrefixes as $prefix) {
        if (strpos($prefix, $childRelativePath . '/') === 0) {
            return true;
        }
    }

    foreach ($visibleFiles as $file) {
        if (strpos($file, $childRelativePath . '/') === 0) {
            return true;
        }
    }

    return false;
}

require_post();

$config = load_repo_config();
require_api_key($config['api_key']);

$body = read_json_body();
$inputPath = $body['path'] ?? '';

if (!is_string($inputPath)) {
    fail(400, 'Missing or invalid path');
}

reject_dangerous_input($inputPath);

$relativePath = normalize_relative_path($inputPath);
$absolutePath = build_absolute_path($config['repo_root'], $relativePath);
$resolvedPath = realpath($absolutePath);

if ($resolvedPath === false || !is_dir($resolvedPath)) {
    fail(404, 'Directory not found', ['path' => $relativePath]);
}

$resolvedPath = str_replace('\\', '/', $resolvedPath);
ensure_within_repo_root($config['repo_root'], $resolvedPath);

if ($relativePath !== '' && !is_visible_path($relativePath, $config['visible_prefixes'], $config['visible_files'])) {
    fail(403, 'Path is not visible', ['path' => $relativePath]);
}

$entries = [];
$items = scandir($resolvedPath);

if ($items === false) {
    fail(500, 'Unable to read directory', ['path' => $relativePath]);
}

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

respond(200, [
    'status' => 'ok',
    'path' => $relativePath,
    'entries' => $entries,
]);