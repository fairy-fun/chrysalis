<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/private/base_endpoint.php';

['pdo' => $pdo] = endpoint_bootstrap('POST', 'public');

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

    $expectedDatabase = db_name();

    $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ((string) $activeDatabase !== $expectedDatabase) {
        fail(500, 'Unexpected database selected', [
            'active_database'   => $activeDatabase,
            'expected_database' => $expectedDatabase,
        ]);
    }

    $sql = rtrim($sql);
    $sql = rtrim($sql, ';');

    $normalizedSql = strtolower(ltrim($sql));

    if (!str_starts_with($normalizedSql, 'select')) {
        fail(400, 'Only SELECT queries are allowed');
    }

    $forbiddenPatterns = [
        '/\binsert\b/i',
        '/\bupdate\b/i',
        '/\bdelete\b/i',
        '/\bdrop\b/i',
        '/\balter\b/i',
        '/\btruncate\b/i',
        '/\bcreate\b/i',
        '/\breplace\b/i',
        '/\brename\b/i',
        '/\bgrant\b/i',
        '/\brevoke\b/i',
        '/\block\b/i',
        '/\bunlock\b/i',
        '/\bset\b/i',
        '/\bcall\b/i',
        '/\buse\b/i',
        '/\boutfile\b/i',
        '/\bdumpfile\b/i',
        '/--/',
        '/#/',
        '/\/\*/',
    ];

    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $sql)) {
            fail(400, 'Disallowed query');
        }
    }

    if (!preg_match('/\blimit\s+\d+\b/i', $sql)) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'status'        => 'ok',
        'source'        => 'database',
        'database'      => $expectedDatabase,
        'row_count'     => count($rows),
        'limit_applied' => $limit,
        'sql_executed'  => $sql,
        'rows'          => $rows,
    ]);
} catch (Throwable $e) {
    log_api_error($e);
    fail(500, 'Internal server error');
}