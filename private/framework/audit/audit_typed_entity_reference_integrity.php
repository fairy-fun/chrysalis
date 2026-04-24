<?php

declare(strict_types=1);

function audit_typed_entity_reference_integrity(PDO $pdo, string $schemaName): array
{
    $references = [
        ['character_profiles', 'profile_type_id', 'entity_type_profile_type'],
        ['profile_type_priority', 'profile_type_id', 'entity_type_profile_type'],
        ['profile_type_domain_map', 'profile_type_id', 'entity_type_profile_type'],

        ['choreography_progress_history', 'new_status_id', 'entity_type_status'],
        ['choreography_progress_history', 'previous_status_id', 'entity_type_status'],
        ['company_assignments', 'status_id', 'entity_type_status'],
        ['relationship_status_history', 'status_id', 'entity_type_status'],
        ['status_history', 'status_id', 'entity_type_status'],
        ['team_admin_assignments', 'status_id', 'entity_type_status'],
        ['team_memberships', 'status_id', 'entity_type_status'],

        ['figures', 'classval_id', 'entity_type_figure'],
    ];

    $violations = [];
    $queryErrors = [];

    foreach ($references as [$tableName, $columnName, $expectedEntityTypeId]) {
        $sql = "
            SELECT
                '{$tableName}' AS table_name,
                '{$columnName}' AS column_name,
                '{$expectedEntityTypeId}' AS expected_entity_type_id,
                t.{$columnName} AS referenced_entity_id,
                e.entity_type_id AS actual_entity_type_id,
                COUNT(*) AS reference_count
            FROM {$schemaName}.{$tableName} t
            LEFT JOIN {$schemaName}.entities e
                ON e.id COLLATE utf8mb4_general_ci = t.{$columnName} COLLATE utf8mb4_general_ci
            WHERE t.{$columnName} IS NOT NULL
              AND t.{$columnName} <> ''
              AND (
                  e.id IS NULL
                  OR e.entity_type_id <> '{$expectedEntityTypeId}'
              )
            GROUP BY t.{$columnName}, e.entity_type_id
            ORDER BY t.{$columnName}
        ";

        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $violations[] = $row;
            }
        } catch (Throwable $e) {
            $queryErrors[] = [
                'table_name' => $tableName,
                'column_name' => $columnName,
                'expected_entity_type_id' => $expectedEntityTypeId,
                'error' => $e->getMessage(),
            ];
        }
    }

    return [
        'ok' => count($violations) === 0 && count($queryErrors) === 0,
        'schema_name' => $schemaName,
        'reference_count' => count($references),
        'violation_count' => count($violations),
        'query_error_count' => count($queryErrors),
        'violations' => $violations,
        'query_errors' => $queryErrors,
    ];
}

function assert_typed_entity_reference_integrity(PDO $pdo, string $schemaName): void
{
    $audit = audit_typed_entity_reference_integrity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    throw new RuntimeException(
        'Typed entity reference integrity audit failed: violations='
        . (string)$audit['violation_count']
        . ', query errors='
        . (string)$audit['query_error_count']
    );
}
