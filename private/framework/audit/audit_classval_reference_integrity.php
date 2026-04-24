<?php

declare(strict_types=1);

/*
 * Classval reference promotion policy:
 *
 * Classval reference columns are promoted from CI-audited references
 * to schema-enforced foreign keys only when they are:
 *
 * - stable
 * - required for core logic
 * - already clean under audit
 *
 * Columns that are dynamic, legacy, sparsely used, or still being
 * classified remain audit-only until their ownership and semantics are
 * clear.
 *
 * Locked principle:
 *
 *     Database enforces stable structural references.
 *     CI audits classify and enforce the remaining semantic surface.
 *
 * Do not add broad classval foreign keys without first classifying the
 * column as FK-safe.
 */

function audit_classval_reference_integrity(PDO $pdo, string $schemaName): array
{

    // Classval reference classification:
    //
    // DB FK-backed references:
    // - attribute_type_layer_map.layer_classval_id
    // - character_profile_attributes.value_classval_id
    // - classval_tag_map.classval_id
    // - identity_context_alias_map.alias_type_classval_id
    //
    // Intentionally audit-only references:
    // These remain in CI because they are rule-driven, semantically complex,
    // or still evolving as part of the expression constraint engine.

        $references = [
            // FK-backed: keep listed here for visibility/audit reporting, but DB now enforces existence.
            ['attribute_type_layer_map', 'layer_classval_id', 'FK'],
            ['character_profile_attributes', 'value_classval_id', 'FK'],
            ['classval_tag_map', 'classval_id', 'FK'],
            ['identity_context_alias_map', 'alias_type_classval_id', 'FK'],

            // Audit-only: expression constraint surface.
            ['expression_constraint_outputs', 'access_state_classval_id', 'AUDIT'],
            ['expression_constraint_outputs', 'constraint_effect_type_classval_id', 'AUDIT'],
            ['expression_constraint_outputs', 'constraint_strength_classval_id', 'AUDIT'],
            ['expression_constraint_outputs', 'input_value_classval_id', 'AUDIT'],
            ['expression_constraint_outputs', 'output_value_classval_id', 'AUDIT'],

            ['expression_constraint_rule_conditions', 'comparator_classval_id', 'AUDIT'],
            ['expression_constraint_rule_conditions', 'expected_value_classval_id', 'AUDIT'],

            ['expression_constraint_rule_effects', 'access_state_classval_id', 'AUDIT'],
            ['expression_constraint_rule_effects', 'constraint_effect_type_classval_id', 'AUDIT'],
            ['expression_constraint_rule_effects', 'output_value_classval_id', 'AUDIT'],

            ['expression_constraint_rules', 'constraint_rule_type_classval_id', 'AUDIT'],
            ['expression_constraint_rules', 'profile_type_classval_id', 'AUDIT'],
            ['expression_constraint_rules', 'strength_classval_id', 'AUDIT'],
    ];


    $violations = [];
    $queryErrors = [];

    foreach ($references as [$tableName, $columnName, $enforcement]) {
        $sql = "
            SELECT
                '{$tableName}' AS table_name,
                '{$columnName}' AS column_name,
                t.{$columnName} AS unresolved_classval_id,
                COUNT(*) AS reference_count
            FROM {$schemaName}.{$tableName} t
            LEFT JOIN {$schemaName}.classvals c
                ON c.id COLLATE utf8mb4_general_ci = t.{$columnName} COLLATE utf8mb4_general_ci
            WHERE t.{$columnName} IS NOT NULL
              AND c.id IS NULL
            GROUP BY t.{$columnName}
            ORDER BY t.{$columnName}
        ";

        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $row['enforcement'] = $enforcement;
                $violations[] = $row;
            }
        } catch (Throwable $e) {
            $queryErrors[] = [
                'table_name' => $tableName,
                'column_name' => $columnName,
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


function assert_classval_reference_integrity(PDO $pdo, string $schemaName): void
{
    $audit = audit_classval_reference_integrity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    throw new RuntimeException(
        'Classval reference integrity audit failed: unresolved references='
        . (string)$audit['violation_count']
    );
}

