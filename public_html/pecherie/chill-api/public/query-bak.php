<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/private/bootstrap.php';

require_method('POST');
enforce_api_key('public');

try {
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false) {
        fail(400, 'Unable to read request body');
    }

    try {
        $input = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        fail(400, 'Invalid JSON body');
    }

    if (!is_array($input)) {
        fail(400, 'Invalid JSON body');
    }

    $sql = isset($input['sql']) && is_string($input['sql'])
        ? trim($input['sql'])
        : '';

    $limit = isset($input['limit']) && is_int($input['limit'])
        ? $input['limit']
        : 100;

    if ($sql === '') {
        fail(400, 'Missing SQL query');
    }

    if ($limit < 1 || $limit > 1000) {
        fail(400, 'Invalid limit');
    }

    $pdo = db();
    $expectedDatabase = db_name();

    $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ((string) $activeDatabase !== $expectedDatabase) {
        fail(500, 'Unexpected database selected', [
            'active_database' => $activeDatabase,
            'expected_database' => $expectedDatabase,
        ]);
    }

    $sql = rtrim($sql);
    $sql = rtrim($sql, ';');

    $normalizedSql = strtolower(ltrim($sql));

    if (!str_starts_with($normalizedSql, 'select')) {
        fail(400, 'Only SELECT queries are allowed');
    }

    $forbidden = [
        'insert ',
        'update ',
        'delete ',
        'drop ',
        'alter ',
        'truncate ',
        'create ',
        'replace ',
        'rename ',
        'grant ',
        'revoke ',
        'lock ',
        'unlock ',
        'set ',
    ];

    foreach ($forbidden as $keyword) {
        if (str_contains($normalizedSql, $keyword)) {
            fail(400, 'Disallowed query', [
                'matched_keyword' => trim($keyword),
            ]);
        }
    }

    if (!preg_match('/\blimit\s+\d+\b/i', $sql)) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'status' => 'ok',
        'source' => 'database',
        'database' => $expectedDatabase,
        'row_count' => count($rows),
        'limit_applied' => $limit,
        'sql_executed' => $sql,
        'rows' => $rows,
    ]);
} catch (Throwable $e) {
    error_log('[chill-api/query.php] ' . $e->getMessage());

    fail(500, 'Internal server error');
}