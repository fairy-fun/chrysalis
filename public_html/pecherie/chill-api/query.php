<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const DEBUG_MODE = false;

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function debugRespond(int $statusCode, array $payload, ?Throwable $e = null): void
{
    if (DEBUG_MODE && $e !== null) {
        $payload['exception'] = get_class($e);
        $payload['message'] = $e->getMessage();
    }

    respond($statusCode, $payload);
}

function getConfig(): array
{
    $config = require __DIR__ . '/../../../pecherie_config.php';

    if (!is_array($config)) {
        respond(500, ['error' => 'Invalid server configuration']);
    }

    return $config;
}

function requireAuth(): void
{
    $config = getConfig();
    $expected = trim((string) ($config['pecherie_api_key'] ?? ''));

    if ($expected === '') {
        respond(500, ['error' => 'Server auth is not configured']);
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided = null;

    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === 'x-api-key') {
            $provided = trim((string) $value);
            break;
        }
    }

    if ($provided === null && isset($_SERVER['HTTP_X_API_KEY'])) {
        $provided = trim((string) $_SERVER['HTTP_X_API_KEY']);
    }

    if ($provided === null || !hash_equals($expected, $provided)) {
        respond(401, ['error' => 'Unauthorized']);
    }
}

function getDatabaseConfig(): array
{
    $config = getConfig();

    if (!isset($config['db']) || !is_array($config['db'])) {
        respond(500, ['error' => 'Database configuration is missing']);
    }

    $db = $config['db'];

    $host = trim((string) ($db['host'] ?? ''));
    $name = trim((string) ($db['name'] ?? ''));
    $user = trim((string) ($db['user'] ?? ''));
    $pass = (string) ($db['pass'] ?? '');
    $charset = trim((string) ($db['charset'] ?? 'utf8mb4'));

    if ($host === '' || $name === '' || $user === '') {
        respond(500, ['error' => 'Database configuration is incomplete']);
    }

    return [
        'host' => $host,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'charset' => $charset !== '' ? $charset : 'utf8mb4',
    ];
}

function getJsonBody(): array
{
    /*
     * New canonical shared request body from chill-api/index.php.
     */
    if (array_key_exists('_API_BODY', $GLOBALS) && is_array($GLOBALS['_API_BODY'])) {
        return $GLOBALS['_API_BODY'];
    }

    /*
     * Legacy compatibility bridge for callers that still set _QUERY_BODY.
     */
    if (array_key_exists('_QUERY_BODY', $GLOBALS) && is_array($GLOBALS['_QUERY_BODY'])) {
        return $GLOBALS['_QUERY_BODY'];
    }

    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        $GLOBALS['_QUERY_BODY'] = [];
        return $GLOBALS['_QUERY_BODY'];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        respond(400, ['error' => 'Request body must be valid JSON']);
    }

    /*
     * Preserve the legacy cache key for compatibility with any existing code,
     * while also making the parsed body available through the new shared key.
     */
    $GLOBALS['_QUERY_BODY'] = $decoded;
    $GLOBALS['_API_BODY'] = $decoded;

    return $decoded;
}

function normaliseSql(string $sql): string
{
    $sql = trim($sql);
    $sql = preg_replace('/\s+/', ' ', $sql);
    $sql = preg_replace('/;+\s*$/', '', $sql);

    return $sql ?? '';
}

function isAllowedReadOnlyQuery(string $sql): bool
{
    return preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $sql) === 1;
}

function containsForbiddenPatterns(string $sql): bool
{
    $forbiddenPatterns = [
        '/;/',
        '/\bINSERT\b/i',
        '/\bUPDATE\b/i',
        '/\bDELETE\b/i',
        '/\bREPLACE\b/i',
        '/\bUPSERT\b/i',
        '/\bALTER\b/i',
        '/\bDROP\b/i',
        '/\bTRUNCATE\b/i',
        '/\bCREATE\b/i',
        '/\bRENAME\b/i',
        '/\bGRANT\b/i',
        '/\bREVOKE\b/i',
        '/\bLOCK\b/i',
        '/\bUNLOCK\b/i',
        '/\bCALL\b/i',
        '/\bHANDLER\b/i',
        '/\bLOAD_FILE\b/i',
        '/\bINTO\s+OUTFILE\b/i',
        '/\bINTO\s+DUMPFILE\b/i',
        '/--/',
        '/#/',
        '/\/\*/',
    ];

    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $sql) === 1) {
            return true;
        }
    }

    return false;
}

function applyLimitToSql(string $sql, int $limit): string
{
    $trimmed = rtrim($sql);

    if (preg_match('/\bLIMIT\s+\d+(\s*,\s*\d+)?\s*$/i', $trimmed) === 1) {
        return $trimmed;
    }

    if (preg_match('/^(SELECT|WITH)\b/i', $trimmed) === 1) {
        return $trimmed . ' LIMIT ' . $limit;
    }

    return $trimmed;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

if (DEBUG_MODE) {
    respond(200, [
        'debug' => true,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'has_api_key_header' => isset($_SERVER['HTTP_X_API_KEY']),
        'received_keys' => array_keys($body),
        'body' => $body,
    ]);
}

$sqlRaw = null;
$sqlFieldUsed = null;

if (isset($body['sql']) && is_string($body['sql'])) {
    $sqlRaw = $body['sql'];
    $sqlFieldUsed = 'sql';
} elseif (isset($body['query']) && is_string($body['query'])) {
    $sqlRaw = $body['query'];
    $sqlFieldUsed = 'query';
}

$sql = is_string($sqlRaw) ? normaliseSql($sqlRaw) : '';

if ($sql === '') {
    respond(400, [
        'error' => 'Missing required sql/query field',
        'received_keys' => array_keys($body),
        'body' => $body,
    ]);
}

if (!isAllowedReadOnlyQuery($sql)) {
    respond(400, ['error' => 'Only read-only SELECT, SHOW, DESCRIBE, EXPLAIN, and WITH queries are allowed']);
}

if (containsForbiddenPatterns($sql)) {
    respond(400, ['error' => 'Query contains forbidden SQL patterns']);
}

$limit = 200;

if (array_key_exists('limit', $body)) {
    if (!is_int($body['limit'])) {
        respond(400, ['error' => 'limit must be an integer']);
    }

    if ($body['limit'] < 1 || $body['limit'] > 1000) {
        respond(400, ['error' => 'limit must be between 1 and 1000']);
    }

    $limit = $body['limit'];
}

$db = getDatabaseConfig();
$expectedDatabase = $db['name'];

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $e) {
    debugRespond(500, ['error' => 'Database connection failed'], $e);
}

try {
    $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ((string) $activeDatabase !== $expectedDatabase) {
        respond(500, [
            'error' => 'Unexpected database selected',
            'details' => "Expected {$expectedDatabase}, got {$activeDatabase}",
        ]);
    }
} catch (Throwable $e) {
    debugRespond(500, ['error' => 'Failed to verify active database'], $e);
}

try {
    $sqlToRun = applyLimitToSql($sql, $limit);
    $stmt = $pdo->query($sqlToRun);
    $rows = $stmt->fetchAll();

    respond(200, [
        'status' => 'ok',
        'source' => 'database',
        'database' => $expectedDatabase,
        'input_field' => $sqlFieldUsed,
        'received_keys' => array_keys($body),
        'row_count' => count($rows),
        'limit_applied' => $limit,
        'rows' => $rows,
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Query failed',
        'database' => $expectedDatabase,
        'input_field' => $sqlFieldUsed,
        'received_keys' => array_keys($body),
    ], $e);
}