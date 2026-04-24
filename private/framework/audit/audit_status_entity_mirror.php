<?php

declare(strict_types=1);

function audit_status_entity_mirror(PDO $pdo, string $schemaName): array
{
    $columnStmt = $pdo->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :schema_name
           AND DATA_TYPE IN ('char', 'varchar')
           AND (COLUMN_NAME = 'status_id' OR COLUMN_NAME LIKE '%\\_status_id')
           AND COLUMN_NAME <> 'status_year_id'
         ORDER BY TABLE_NAME, COLUMN_NAME"
    );

    $columnStmt->execute([':schema_name' => $schemaName]);
    $columns = $columnStmt->fetchAll(PDO::FETCH_ASSOC);

    $missingEntities = [];
    $wrongTypeEntities = [];

    foreach ($columns as $column) {
        $tableName = (string)$column['TABLE_NAME'];
        $columnName = (string)$column['COLUMN_NAME'];

        $missingSql = "
            SELECT DISTINCT
                t.{$columnName} AS status_id,
                '{$tableName}' AS source_table,
                '{$columnName}' AS source_column
            FROM {$schemaName}.{$tableName} t
            LEFT JOIN {$schemaName}.entities e
                ON e.id = t.{$columnName}
            WHERE t.{$columnName} IS NOT NULL
              AND t.{$columnName} <> ''
              AND e.id IS NULL
            ORDER BY t.{$columnName}
        ";

        $wrongTypeSql = "
            SELECT DISTINCT
                t.{$columnName} AS status_id,
                '{$tableName}' AS source_table,
                '{$columnName}' AS source_column,
                e.entity_type_id AS actual_entity_type_id
            FROM {$schemaName}.{$tableName} t
            JOIN {$schemaName}.entities e
                ON e.id = t.{$columnName}
            WHERE t.{$columnName} IS NOT NULL
              AND t.{$columnName} <> ''
              AND e.entity_type_id <> 'entity_type_status'
            ORDER BY t.{$columnName}
        ";

        $missingEntities = array_merge(
            $missingEntities,
            $pdo->query($missingSql)->fetchAll(PDO::FETCH_ASSOC)
        );

        $wrongTypeEntities = array_merge(
            $wrongTypeEntities,
            $pdo->query($wrongTypeSql)->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    return [
        'ok' => count($missingEntities) === 0 && count($wrongTypeEntities) === 0,
        'schema_name' => $schemaName,
        'expected_entity_type_id' => 'entity_type_status',
        'audited_columns' => $columns,
        'missing_entity_count' => count($missingEntities),
        'wrong_type_count' => count($wrongTypeEntities),
        'missing_entities' => $missingEntities,
        'wrong_type_entities' => $wrongTypeEntities,
    ];
}

function assert_status_entity_mirror(PDO $pdo, string $schemaName): void
{
    $audit = audit_status_entity_mirror($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Status entity mirror audit failed: missing mirrors='
        . (string)$audit['missing_entity_count']
        . ', wrong type='
        . (string)$audit['wrong_type_count']
    );
}
