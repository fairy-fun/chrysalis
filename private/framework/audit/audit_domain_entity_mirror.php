<?php

declare(strict_types=1);

function audit_domain_entity_mirror(PDO $pdo, string $schemaName): array
{
    $tables = [
        'attribute_domain_map',
        'profile_type_domain_map',
    ];

    $existingTables = [];

    $tableCheck = $pdo->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = :schema_name
          AND TABLE_NAME = :table_name
        LIMIT 1
    ");

    foreach ($tables as $table) {
        $tableCheck->execute([
            ':schema_name' => $schemaName,
            ':table_name' => $table,
        ]);

        if ($tableCheck->fetchColumn() !== false) {
            $existingTables[] = $table;
        }
    }

    if ($existingTables === []) {
        return [
            'ok' => true,
            'audit' => 'domain_entity_mirror',
            'checked_tables' => [],
            'violations' => [],
        ];
    }

    $unionParts = [];

    foreach ($existingTables as $table) {
        $quotedTable = str_replace('`', '``', $table);

        $unionParts[] = "
            SELECT
                '{$table}' AS source_table,
                domain_id
            FROM `{$quotedTable}`
            WHERE domain_id <> ''
        ";
    }

    $sql = "
        SELECT DISTINCT
            d.source_table,
            d.domain_id
        FROM (
            " . implode("\nUNION\n", $unionParts) . "
        ) d
        LEFT JOIN entities e
            ON e.id = d.domain_id
           AND e.entity_type_id = 'entity_type_domain'
        WHERE e.id IS NULL
        ORDER BY d.source_table, d.domain_id
    ";

    $stmt = $pdo->query($sql);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'ok' => count($violations) === 0,
        'audit' => 'domain_entity_mirror',
        'checked_tables' => $existingTables,
        'violations' => $violations,
    ];
}

function assert_domain_entity_mirror(PDO $pdo, string $schemaName): void
{
    $result = audit_domain_entity_mirror($pdo, $schemaName);

    if ($result['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Domain entity mirror audit failed: ' .
        json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}