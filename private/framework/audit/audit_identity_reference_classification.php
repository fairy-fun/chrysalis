<?php

declare(strict_types=1);

/*
 * Identity Reference Classification Contract
 *
 * Locked rule:
 *
 * - Identity references point to entities.id
 * - Classification references point to classvals.id
 * - Domain references point to domain entities:
 *      entities.id
 *      entities.entity_type_id = 'entity_type_domain'
 *
 * Database enforces existence where references are stable.
 * CI enforces meaning and classification.
 * No triggers.
 */

function audit_identity_reference_classification(PDO $pdo, string $schemaName): array
{
    $references = [
        // identity layer
        ['attribute_domain_map', 'domain_id', 'DOMAIN_ENTITY'],
        ['profile_type_domain_map', 'domain_id', 'DOMAIN_ENTITY'],

        // typed value layer
        ['attribute_type_layer_map', 'layer_classval_id', 'CLASSVAL'],
        ['character_profile_attributes', 'value_classval_id', 'CLASSVAL'],
        ['classval_tag_map', 'classval_id', 'CLASSVAL'],
        ['identity_context_alias_map', 'alias_type_classval_id', 'CLASSVAL'],
    ];

    $violations = [];
    $queryErrors = [];

    foreach ($references as [$tableName, $columnName, $expectedKind]) {
        if ($expectedKind === 'DOMAIN_ENTITY') {
            $sql = "
                SELECT
                    '{$tableName}' AS table_name,
                    '{$columnName}' AS column_name,
                    '{$expectedKind}' AS expected_kind,
                    t.{$columnName} AS invalid_value,
                    e.entity_type_id AS actual_entity_type_id,
                    COUNT(*) AS reference_count
                FROM {$schemaName}.{$tableName} t
                LEFT JOIN {$schemaName}.entities e
                    ON e.id COLLATE utf8mb4_general_ci = t.{$columnName} COLLATE utf8mb4_general_ci
                WHERE t.{$columnName} IS NOT NULL
                  AND t.{$columnName} <> ''
                  AND (
                      e.id IS NULL
                      OR e.entity_type_id <> 'entity_type_domain'
                  )
                GROUP BY t.{$columnName}, e.entity_type_id
                ORDER BY t.{$columnName}
            ";
        } elseif ($expectedKind === 'CLASSVAL') {
            $sql = "
                SELECT
                    '{$tableName}' AS table_name,
                    '{$columnName}' AS column_name,
                    '{$expectedKind}' AS expected_kind,
                    t.{$columnName} AS invalid_value,
                    NULL AS actual_entity_type_id,
                    COUNT(*) AS reference_count
                FROM {$schemaName}.{$tableName} t
                LEFT JOIN {$schemaName}.classvals c
                    ON c.id COLLATE utf8mb4_general_ci = t.{$columnName} COLLATE utf8mb4_general_ci
                WHERE t.{$columnName} IS NOT NULL
                  AND t.{$columnName} <> ''
                  AND c.id IS NULL
                GROUP BY t.{$columnName}
                ORDER BY t.{$columnName}
            ";
        } else {
            $queryErrors[] = [
                'table_name' => $tableName,
                'column_name' => $columnName,
                'error' => 'Unknown expected kind: ' . $expectedKind,
            ];
            continue;
        }

        try {
            $stmt = $pdo->query($sql);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $violations[] = $row;
            }
        } catch (Throwable $e) {
            $queryErrors[] = [
                'table_name' => $tableName,
                'column_name' => $columnName,
                'expected_kind' => $expectedKind,
                'error' => $e->getMessage(),
            ];
        }
    }

    return [
        'ok' => count($violations) === 0 && count($queryErrors) === 0,
        'schema_name' => $schemaName,
        'violation_count' => count($violations),
        'query_error_count' => count($queryErrors),
        'violations' => $violations,
        'query_errors' => $queryErrors,
    ];
}

function assert_identity_reference_classification(PDO $pdo, string $schemaName): void
{
    $audit = audit_identity_reference_classification($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    throw new RuntimeException(
        'Identity reference classification audit failed: violations='
        . (string)$audit['violation_count']
    );
}