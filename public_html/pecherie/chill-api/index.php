<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function api_error(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode([
        'status' => 'error',
        'error' => $message,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false) {
    api_error(400, 'Unable to read request body');
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    api_error(400, 'Invalid JSON body');
}

$operation = $body['operation'] ?? $body['endpoint'] ?? '';
if (!is_string($operation) || $operation === '') {
    api_error(400, 'Missing operation');
}

/*
 * Canonical shared parsed request body for internal handlers.
 */
$GLOBALS['_API_BODY'] = $body;

/*
 * Temporary compatibility bridge for legacy query.php callers/assumptions.
 * Remove later once query.php no longer depends on this shape.
 */
$GLOBALS['_QUERY_BODY'] = $body;

switch ($operation) {
    case 'listRepo':
        require __DIR__ . '/repo/list_repo.php';
        break;

    case 'getRepoFile':
        require __DIR__ . '/repo/get_repo_file.php';
        break;

    case 'executeSqlRead':
        require __DIR__ . '/query.php';
        break;

    /*
     * Temporary legacy operations during migration.
     */
    case 'tables':
        require __DIR__ . '/tables.php';
        break;

    case 'columns':
        require __DIR__ . '/columns.php';
        break;

    case 'query':
        require __DIR__ . '/query.php';
        break;

    default:
        api_error(400, 'Unknown operation: ' . $operation);
}