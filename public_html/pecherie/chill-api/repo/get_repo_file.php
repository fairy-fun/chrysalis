<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const DEBUG_MODE = false;

function respond(int $statusCode, array $payload, array $headers = []): void
{
    http_response_code($statusCode);

    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function fail(
    int $statusCode,
    string $error,
    array $extra = [],
    array $headers = []
): void {
    respond($statusCode, array_merge([
        'status' => 'error',
        'error' => $error,
    ], $extra), $headers);
}

function debugFail(
    int $statusCode,
    string $error,
    array $extra = [],
    ?Throwable $e = null,
    array $headers = []
): void {
    if (DEBUG_MODE && $e !== null) {
        $extra['debug'] = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ];
    }

    fail($statusCode, $error, $extra, $headers);
}

function loadConfig(): array
{
    $configPath = __DIR__ . '/../../../../pecherie_config.php';

    if (!is_file($configPath)) {
        fail(500, 'Missing server configuration');
    }

    $config = require $configPath;

    if (!is_array($config)) {
        fail(500, 'Invalid server configuration');
    }

    return $config;
}

function getHeaderValue(string $headerName): ?string
{
    $target = strtolower($headerName);

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower((string)$key) === $target) {
                    return trim((string)$value);
                }
            }
        }
    }

    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
    if (isset($_SERVER[$serverKey])) {
        return trim((string)$_SERVER[$serverKey]);
    }

    return null;
}

function requireAuth(array $config): void
{
    $expected = trim((string)($config['pecherie_api_key'] ?? ''));

    if ($expected === '') {
        fail(500, 'Server auth is not configured');
    }

    $provided = getHeaderValue('X-API-Key');

    if ($provided === null || $provided === '' || !hash_equals($expected, $provided)) {
        fail(401, 'Unauthorized');
    }
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false) {
        fail(400, 'Unable to read request body');
    }

    $trimmed = trim($raw);
    if ($trimmed === '') {
        return [];
    }

    try {
        $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        debugFail(400, 'Request body must be valid JSON', [], $e);
    }

    if (!is_array($decoded)) {
        fail(400, 'Request body must decode to a JSON object');
    }

    return $decoded;
}

function normaliseRepoPath(string $path): string
{
    $path = trim($path);
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    $path = ltrim($path, '/');

    return $path;
}

function validateRelativePath(string $path): void
{
    if ($path === '') {
        fail(400, 'Missing required path field');
    }

    if (strpos($path, "\0") !== false) {
        fail(400, 'Invalid path');
    }

    if (preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
        fail(403, 'Path traversal is not allowed', [
            'path' => $path,
        ]);
    }
}

function getRepoRoot(array $config): string
{
    $configuredRoot = trim((string)($config['chrysalis_repo_root'] ?? ''));

    if ($configuredRoot === '') {
        fail(500, 'Repo root is not configured', [
            'hint' => 'Add chrysalis_repo_root to pecherie_config.php',
        ]);
    }

    $repoRoot = realpath($configuredRoot);

    if ($repoRoot === false || !is_dir($repoRoot)) {
        fail(500, 'Repo root is not accessible');
    }

    return rtrim($repoRoot, DIRECTORY_SEPARATOR);
}

function resolveFilePath(string $repoRoot, string $relativePath): string
{
    $candidate = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!file_exists($candidate)) {
        fail(404, 'File not found', [
            'path' => $relativePath,
        ]);
    }

    $resolved = realpath($candidate);

    if ($resolved === false) {
        fail(404, 'File not found', [
            'path' => $relativePath,
        ]);
    }

    $repoPrefix = $repoRoot . DIRECTORY_SEPARATOR;

    if (!str_starts_with($resolved, $repoPrefix) && $resolved !== $repoRoot) {
        fail(403, 'Resolved path is outside repo root', [
            'path' => $relativePath,
        ]);
    }

    if (!is_file($resolved)) {
        fail(400, 'Requested path is not a file', [
            'path' => $relativePath,
        ]);
    }

    if (!is_readable($resolved)) {
        fail(403, 'File is not readable', [
            'path' => $relativePath,
        ]);
    }

    return $resolved;
}

function detectMimeType(string $filePath): string
{
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($filePath);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }

    return 'text/plain';
}

function toRepoRelativePath(string $repoRoot, string $resolvedFilePath): string
{
    $prefixLength = strlen($repoRoot) + 1;
    $relative = substr($resolvedFilePath, $prefixLength);

    return str_replace('\\', '/', (string)$relative);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'Method not allowed', [], [
        'Allow' => 'POST',
    ]);
}

try {
    $config = loadConfig();
    requireAuth($config);

    $body = getJsonBody();

    $path = isset($body['path']) && is_string($body['path'])
        ? normaliseRepoPath($body['path'])
        : '';

    validateRelativePath($path);

    $repoRoot = getRepoRoot($config);
    $filePath = resolveFilePath($repoRoot, $path);

    $contents = file_get_contents($filePath);
    if ($contents === false) {
        fail(500, 'Failed to read file', [
            'path' => $path,
        ]);
    }

    $size = filesize($filePath);
    if ($size === false) {
        $size = strlen($contents);
    }

    respond(200, [
        'status' => 'ok',
        'path' => toRepoRelativePath($repoRoot, $filePath),
        'mime_type' => detectMimeType($filePath),
        'size_bytes' => $size,
        'contents' => $contents,
    ]);
} catch (Throwable $e) {
    debugFail(500, 'Unexpected server error', [], $e);
}