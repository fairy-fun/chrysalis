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

    if ($path === '') {
        fail(400, 'Missing path');
    }

    if (str_contains($path, "\0")) {
        fail(400, 'Invalid path');
    }

    if (preg_match('#(^|/)\.\.(/|$)#', $path)) {
        fail(400, 'Parent traversal is not allowed');
    }

    return $path;
}

function assert_allowed_extension(string $path): void
{
    $allowed = ['php', 'md', 'json', 'yml', 'yaml', 'sql', 'txt'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($ext === '' || !in_array($ext, $allowed, true)) {
        fail(400, 'File type is not allowed', [
            'path' => $path,
            'allowed_extensions' => $allowed,
        ]);
    }
}

$requestedPath = $_GET['path'] ?? '';
$relativePath = normalise_repo_path($requestedPath);
assert_allowed_extension($relativePath);

$repoRoot = rtrim(get_repo_root(), DIRECTORY_SEPARATOR);
$fullPath = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
$realRepoRoot = realpath($repoRoot);
$realFilePath = realpath($fullPath);

if ($realRepoRoot === false) {
    fail(500, 'Configured repo root does not exist');
}

if ($realFilePath === false || !is_file($realFilePath)) {
    fail(404, 'File not found', [
        'requested_path' => $relativePath,
    ]);
}

if (strncmp($realFilePath, $realRepoRoot . DIRECTORY_SEPARATOR, strlen($realRepoRoot . DIRECTORY_SEPARATOR)) !== 0) {
    fail(403, 'Resolved path escapes repo root');
}

$content = file_get_contents($realFilePath);
if ($content === false) {
    fail(500, 'Unable to read file', [
        'requested_path' => $relativePath,
    ]);
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'repo_root' => $realRepoRoot,
    'requested_path' => $relativePath,
    'resolved_path' => $realFilePath,
    'content' => $content,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;