<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/private/bootstrap.php';

function fail(int $statusCode, string $message, array $extra = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(array_merge([
        'status' => 'error',
        'message' => $message,
    ], $extra), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

function get_repo_root(): string
{
    return '/home/sxnzlfun/repositories/chrysalis';
}

function normalise_repo_path(string $path): string
{
    $path = trim($path);
    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');

    if (str_contains($path, "\0")) {
        fail(400, 'Invalid path');
    }

    if (preg_match('#(^|/)\.\.(/|$)#', $path)) {
        fail(400, 'Parent traversal is not allowed');
    }

    return $path;
}

$requestedPath = $_GET['path'] ?? '';
$relativePath = normalise_repo_path($requestedPath);

$repoRoot = rtrim(get_repo_root(), DIRECTORY_SEPARATOR);
$basePath = $relativePath === ''
    ? $repoRoot
    : $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

$realRepoRoot = realpath($repoRoot);
$realBasePath = realpath($basePath);

if ($realRepoRoot === false) {
    fail(500, 'Configured repo root does not exist');
}

if ($realBasePath === false || !is_dir($realBasePath)) {
    fail(404, 'Directory not found', [
        'requested_path' => $relativePath,
    ]);
}

if ($realBasePath !== $realRepoRoot
    && strncmp($realBasePath, $realRepoRoot . DIRECTORY_SEPARATOR, strlen($realRepoRoot . DIRECTORY_SEPARATOR)) !== 0) {
    fail(403, 'Resolved path escapes repo root');
}

$items = scandir($realBasePath);
if ($items === false) {
    fail(500, 'Unable to read directory', [
        'requested_path' => $relativePath,
    ]);
}

$entries = [];
foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }

    $itemPath = $realBasePath . DIRECTORY_SEPARATOR . $item;
    $relativeItemPath = ltrim(str_replace($realRepoRoot, '', $itemPath), DIRECTORY_SEPARATOR);
    $relativeItemPath = str_replace(DIRECTORY_SEPARATOR, '/', $relativeItemPath);

    $entries[] = [
        'name' => $item,
        'path' => $relativeItemPath,
        'type' => is_dir($itemPath) ? 'dir' : 'file',
    ];
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'repo_root' => $realRepoRoot,
    'requested_path' => $relativePath,
    'resolved_path' => $realBasePath,
    'entries' => $entries,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;