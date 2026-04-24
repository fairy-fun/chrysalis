<?php

declare(strict_types=1);

function audit_untyped_varchar_id_surface(PDO $pdo, string $schemaName): array
{
    $typedEntityReferences = [
        'character_profiles.profile_type_id',
        'profile_type_priority.profile_type_id',
        'profile_type_domain_map.profile_type_id',
        'choreography_progress_history.new_status_id',
        'choreography_progress_history.previous_status_id',
        'company_assignments.status_id',
        'relationship_status_history.status_id',
        'status_history.status_id',
        'team_admin_assignments.status_id',
        'team_memberships.status_id',
        'figures.classval_id',
    ];

    $classvalReferences = [
        'attribute_type_layer_map.layer_classval_id',
        'character_profile_attributes.value_classval_id',
        'classval_tag_map.classval_id',
        'expression_constraint_outputs.access_state_classval_id',
        'expression_constraint_outputs.constraint_effect_type_classval_id',
        'expression_constraint_outputs.constraint_strength_classval_id',
        'expression_constraint_outputs.input_value_classval_id',
        'expression_constraint_outputs.output_value_classval_id',
        'expression_constraint_rule_conditions.comparator_classval_id',
        'expression_constraint_rule_conditions.expected_value_classval_id',
        'expression_constraint_rule_effects.access_state_classval_id',
        'expression_constraint_rule_effects.constraint_effect_type_classval_id',
        'expression_constraint_rule_effects.output_value_classval_id',
        'expression_constraint_rules.constraint_rule_type_classval_id',
        'expression_constraint_rules.profile_type_classval_id',
        'expression_constraint_rules.strength_classval_id',
        'identity_context_alias_map.alias_type_classval_id',
    ];

    $explicitExceptions = [
        'team_memberships.status_year_id' => 'calendar/year registry, not status registry',
    ];

    $stmt = $pdo->prepare(
        "SELECT
            TABLE_NAME AS table_name,
            COLUMN_NAME AS column_name,
            DATA_TYPE AS data_type,
            COLUMN_TYPE AS column_type,
            IS_NULLABLE AS is_nullable
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :schema_name
           AND DATA_TYPE IN ('char', 'varchar')
           AND COLUMN_NAME LIKE '%\\_id'
           AND TABLE_NAME NOT LIKE 'v\\_%'
           AND TABLE_NAME NOT LIKE 'vw\\_%'
         ORDER BY TABLE_NAME, COLUMN_NAME"
    );

    $stmt->execute([':schema_name' => $schemaName]);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $classified = [];
    $unclassified = [];

    $typedLookup = array_flip($typedEntityReferences);
    $classvalLookup = array_flip($classvalReferences);

    foreach ($columns as $column) {
        $tableName = (string)$column['table_name'];
        $columnName = (string)$column['column_name'];
        $key = $tableName . '.' . $columnName;

        if (isset($typedLookup[$key])) {
            $column['classification'] = 'typed_entity_reference';
            $classified[] = $column;
            continue;
        }

        if (isset($classvalLookup[$key])) {
            $column['classification'] = 'classval_reference';
            $classified[] = $column;
            continue;
        }

        if ($columnName === 'entity_id' || substr($columnName, -10) === '_entity_id') {
            $column['classification'] = 'typed_entity_reference';
            $column['classification_source'] = $columnName === 'entity_id'
                ? 'automatic_exact_rule:entity_id'
                : 'automatic_suffix_rule:_entity_id';
            $classified[] = $column;
            continue;
        }

        if (substr($columnName, -8) === '_type_id') {
            $column['classification'] = 'registry_candidate';
            $column['classification_source'] = 'automatic_suffix_rule:_type_id';
            $classified[] = $column;
            continue;
        }

        if ($columnName === 'domain_id' || substr($columnName, -10) === '_domain_id') {
            $column['classification'] = 'registry_candidate';
            $column['classification_source'] = $columnName === 'domain_id'
                ? 'automatic_exact_rule:domain_id'
                : 'automatic_suffix_rule:_domain_id';
            $classified[] = $column;
            continue;
        }

        if ($columnName === 'domain_id' || substr($columnName, -10) === '_domain_id') {
            $column['classification'] = 'registry_candidate';
            $column['classification_source'] = $columnName === 'domain_id'
                ? 'automatic_exact_rule:domain_id'
                : 'automatic_suffix_rule:_domain_id';
            $classified[] = $column;
            continue;
        }

        if (array_key_exists($key, $explicitExceptions)) {
            $column['classification'] = 'explicit_exception';
            $column['exception_reason'] = $explicitExceptions[$key];
            $classified[] = $column;
            continue;
        }

        $unclassified[] = $column;
    }

    return [
        'ok' => count($unclassified) === 0,
        'schema_name' => $schemaName,
        'varchar_id_column_count' => count($columns),
        'classified_count' => count($classified),
        'unclassified_count' => count($unclassified),
        'unclassified_columns' => $unclassified,
        'classified_columns' => $classified,
    ];
}

function assert_untyped_varchar_id_surface(PDO $pdo, string $schemaName): void
{
    $audit = audit_untyped_varchar_id_surface($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    $failureSummary = [
        'ok' => $audit['ok'],
        'schema_name' => $audit['schema_name'],
        'varchar_id_column_count' => $audit['varchar_id_column_count'],
        'classified_count' => $audit['classified_count'],
        'unclassified_count' => $audit['unclassified_count'],
        'first_unclassified_columns' => array_slice($audit['unclassified_columns'], 0, 50),
    ];

    echo json_encode($failureSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    //echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    throw new RuntimeException(
        'Untyped varchar *_id surface audit failed: unclassified columns='
        . (string)$audit['unclassified_count']
    );
}
