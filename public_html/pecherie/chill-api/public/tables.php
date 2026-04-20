<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/private/base_endpoint.php';

['pdo' => $pdo] = endpoint_bootstrap('GET', 'public');

$expectedDatabase = db_name();

try {
    $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ((string) $activeDatabase !== $expectedDatabase) {
        fail(500, 'Unexpected database selected');
    }
} catch (Throwable $e) {
    log_api_error($e);
    fail(500, 'Failed to verify active database');
}

try {
    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = :database_name
           AND TABLE_TYPE = :table_type
         ORDER BY TABLE_NAME'
    );

    $stmt->execute([
        ':database_name' => $expectedDatabase,
        ':table_type'    => 'BASE TABLE',
    ]);

    $rawTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $tables = array_map(
        static fn ($table): string => (string) $table,
        is_array($rawTables) ? $rawTables : []
    );

    json_response([
        'status'   => 'ok',
        'source'   => 'database',
        'database' => $expectedDatabase,
        'tables'   => $tables,
    ]);
} catch (Throwable $e) {
    log_api_error($e);
    fail(500, 'Failed to fetch tables');
}