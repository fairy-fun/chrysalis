<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/private/base_endpoint.php';

['pdo' => $pdo, 'input' => $input] = endpoint_bootstrap('GET', 'public');

$table = require_string_param($input, 'table');

if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    fail(400, 'Invalid table parameter');
}

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
    $tableCheck = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = :database_name
           AND TABLE_NAME = :table_name'
    );

    $tableCheck->execute([
        ':database_name' => $expectedDatabase,
        ':table_name'    => $table,
    ]);

    $tableExists = (int) $tableCheck->fetchColumn() > 0;

    if (!$tableExists) {
        fail(404, 'Table not found');
    }
} catch (Throwable $e) {
    log_api_error($e);
    fail(500, 'Failed to verify table');
}

try {
    $stmt = $pdo->prepare(
        'SELECT
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_KEY,
            EXTRA
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :database_name
           AND TABLE_NAME = :table_name
         ORDER BY ORDINAL_POSITION'
    );

    $stmt->execute([
        ':database_name' => $expectedDatabase,
        ':table_name'    => $table,
    ]);

    $rawColumns = $stmt->fetchAll();

    $columns = array_map(
        static function (array $column): array {
            return [
                'name'     => (string) $column['COLUMN_NAME'],
                'type'     => (string) $column['COLUMN_TYPE'],
                'nullable' => strtoupper((string) $column['IS_NULLABLE']) === 'YES',
                'key'      => (string) $column['COLUMN_KEY'],
                'extra'    => (string) $column['EXTRA'],
            ];
        },
        $rawColumns
    );

    json_response([
        'status'   => 'ok',
        'source'   => 'database',
        'database' => $expectedDatabase,
        'table'    => $table,
        'columns'  => $columns,
    ]);
} catch (Throwable $e) {
    log_api_error($e);
    fail(500, 'Failed to fetch column definitions');
}