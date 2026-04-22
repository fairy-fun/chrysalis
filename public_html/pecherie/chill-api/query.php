<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../private/framework/api/api_bootstrap.php';

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
$receivedKeys = array_keys($body);

$debugRequested = isset($body['debug']) && $body['debug'] === true;

if (DEBUG_MODE && $debugRequested) {
    respond(200, [
        'debug' => true,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'has_api_key_header' => isset($_SERVER['HTTP_X_API_KEY']),
        'received_keys' => $receivedKeys,
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
        'received_keys' => $receivedKeys,
    ]);
}

if (!isAllowedReadOnlyQuery($sql)) {
    respond(400, [
        'error' => 'Only read-only SELECT, SHOW, DESCRIBE, EXPLAIN, and WITH queries are allowed',
        'input_field' => $sqlFieldUsed,
    ]);
}

if (containsForbiddenPatterns($sql)) {
    respond(400, [
        'error' => 'Query contains forbidden SQL patterns',
        'input_field' => $sqlFieldUsed,
    ]);
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

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $sqlToRun = applyLimitToSql($sql, $limit);
    $stmt = $pdo->query($sqlToRun);

    if ($stmt === false) {
        respond(500, [
            'error' => 'Query failed',
            'database' => $expectedDatabase,
            'input_field' => $sqlFieldUsed,
            'received_keys' => $receivedKeys,
        ]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        'status' => 'ok',
        'source' => 'database',
        'database' => $expectedDatabase,
        'input_field' => $sqlFieldUsed,
        'received_keys' => $receivedKeys,
        'row_count' => count($rows),
        'limit_applied' => $limit,
        'rows' => $rows,
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Query failed',
        'database' => $expectedDatabase,
        'input_field' => $sqlFieldUsed,
        'received_keys' => $receivedKeys,
    ], $e);
}