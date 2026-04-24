<?php

declare(strict_types=1);

function audit_classval_reference_integrity(PDO $pdo, string $schemaName): array
{
    $references = [
        ['attribute_type_layer_map', 'layer_classval_id'],
        ['character_profile_attributes', 'value_classval_id'],
        ['character_profiles', 'profile_type_id'],
        ['choreography_hierarchy', 'child_group_classval_id'],
        ['classval_tag_map', 'classval_id'],
        ['dancer_choreography_status', 'status_classval_id'],
        ['entity_linked_fact_qualifiers', 'value_classval_id'],
        ['entity_linked_facts', 'fact_type_id'],
        ['expression_constraint_outputs', 'access_state_classval_id'],
        ['expression_constraint_outputs', 'constraint_effect_type_classval_id'],
        ['expression_constraint_outputs', 'constraint_strength_classval_id'],
        ['expression_constraint_outputs', 'input_value_classval_id'],
        ['expression_constraint_outputs', 'output_value_classval_id'],
        ['expression_constraint_rule_conditions', 'comparator_classval_id'],
        ['expression_constraint_rule_conditions', 'expected_value_classval_id'],
        ['expression_constraint_rule_effects', 'access_state_classval_id'],
        ['expression_constraint_rule_effects', 'constraint_effect_type_classval_id'],
        ['expression_constraint_rule_effects', 'output_value_classval_id'],
        ['expression_constraint_rules', 'constraint_rule_type_classval_id'],
        ['expression_constraint_rules', 'profile_type_classval_id'],
        ['expression_constraint_rules', 'strength_classval_id'],
        ['figures', 'classval_id'],
        ['identity_context_alias_map', 'alias_type_classval_id'],
        ['journal_type_classvals', 'classval_id'],
        ['nl_intent_directives', 'intent_classval_id'],
        ['nl_intent_traversals', 'intent_classval_id'],
        ['nl_phrase_patterns', 'intent_classval_id'],
        ['performance_routines', 'status_classval_id'],
        ['profile_type_domain_map', 'profile_type_id'],
        ['profile_type_priority', 'profile_type_id'],
        ['projection_type_classvals', 'classval_id'],
        ['relationship_status_history', 'status_id'],
        ['relationships', 'status_classval_id'],
        ['segment_groups', 'group_classval_id'],
        ['segment_pairings', 'status_classval_id'],
        ['semantic_aliases', 'classval_id'],
        ['status_history', 'status_id'],
        ['team_choreography_assignments', 'status_classval_id'],
        ['team_choreography_status', 'status_classval_id'],
        ['team_memberships', 'status_id'],
        ['teams', 'team_domain_classval_id'],
    ];

    $violations = [];

    foreach ($references as [$tableName, $columnName]) {
        $sql = "
            SELECT
                '{$tableName}' AS table_name,
                '{$columnName}' AS column_name,
                t.{$columnName} AS unresolved_classval_id,
                COUNT(*) AS reference_count
            FROM {$schemaName}.{$tableName} t
            LEFT JOIN {$schemaName}.classvals c
                ON c.id = t.{$columnName}
            WHERE t.{$columnName} IS NOT NULL
              AND c.id IS NULL
            GROUP BY t.{$columnName}
            ORDER BY t.{$columnName}
        ";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $violations[] = $row;
        }
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'violation_count' => count($violations),
        'violations' => $violations,
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

/*function assert_classval_reference_integrity(PDO $pdo, string $schemaName): void
{
    $audit = audit_classval_reference_integrity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Classval reference integrity audit failed: unresolved references='
        . (string)$audit['violation_count']
    );
}*/