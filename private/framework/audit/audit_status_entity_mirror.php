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
           AND COLUMN_NAME <> 'status_year_id'"
    );

    $columnStmt->execute([':schema_name' => $schemaName]);
    $columns = $columnStmt->fetchAll(PDO::FETCH_ASSOC);

    $missingEntities = [];
    $wrongTypeEntities = [];

    foreach ($columns as $column) {
        $tableName = (string)$column['TABLE_NAME'];
        $columnName = (string)$column['COLUMN_NAME'];

        $missingSql = "SELECT DISTINCT t.{$columnName} AS status_id FROM {$schemaName}.{$tableName} t LEFT JOIN {$schemaName}.entities e ON e.id = t.{$columnName} WHERE t.{$columnName} IS NOT NULL AND t.{$columnName} <> '' AND e.id IS NULL";

        $wrongTypeSql = "SELECT DISTINCT t.{$columnName} AS status_id, e.entity_type_id FROM {$schemaName}.{$tableName} t JOIN {$schemaName}.entities e ON e.id = t.{$columnName} WHERE t.{$columnName} IS NOT NULL AND t.{$columnName} <> '' AND e.entity_type_id <> 'entity_type_status'";

        $missingEntities = array_merge($missingEntities, $pdo->query($missingSql)->fetchAll(PDO::FETCH_ASSOC));
        $wrongTypeEntities = array_merge($wrongTypeEntities, $pdo->query($wrongTypeSql)->fetchAll(PDO::FETCH_ASSOC));
    }

    return [
        'ok' => count($missingEntities) === 0 && count($wrongTypeEntities) === 0,
        'missing_entity_count' => count($missingEntities),
        'wrong_type_count' => count($wrongTypeEntities),
    ];
}

function assert_status_entity_mirror(PDO $pdo, string $schemaName): void
{
    $audit = audit_status_entity_mirror($pdo, $schemaName);

    if ($audit['ok']) return;

    throw new RuntimeException('Status entity mirror audit failed');
}
