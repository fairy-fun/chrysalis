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
 *
 * Reference promotion pattern:
 *
 * See:
 * private/docs/reference_promotion_pattern.md
 */

function audit_identity_reference_classification(PDO $pdo, string $schemaName): array
{
    /*
     * DOMAIN ENTITY REFERENCES (PROMOTED)
     *
     * DOMAIN_ENTITY_FK indicates:
     *
     * - Database enforces existence via FK → entities.id
     * - CI enforces semantic correctness:
     *       entity_type_id = 'entity_type_domain'
     *
     * This is no longer a soft classification.
     * It is a split enforcement model:
     *
     *     DB → existence
     *     CI → type correctness
     *
     * Do not downgrade DOMAIN_ENTITY_FK references back to audit-only.
     */
    $references = [



        // domain entity references
        ['attribute_domain_map', 'domain_id', 'DOMAIN_ENTITY_FK'],
        ['attribute_domains', 'domain_id', 'DOMAIN_ENTITY_FK'],
        ['calendar_events', 'domain_id', 'DOMAIN_ENTITY_FK'],
        ['calendar_events_backup_pre_repair', 'domain_id', 'DOMAIN_ENTITY_FK'],
        ['calendar_events_old', 'domain_id', 'DOMAIN_ENTITY_FK'],
        ['calendar_domain_beat_classset_map', 'domain_id', 'DOMAIN_CLASSVAL'],
        ['calendar_records', 'domain_id', 'DOMAIN_ENTITY_FK'],
        ['expression_domain_aliases', 'input_domain_id', 'DOMAIN_ENTITY_FK'],
        ['expression_domain_aliases', 'target_domain_id', 'DOMAIN_ENTITY_FK'],
        ['idea_classifications', 'domain_id', 'DOMAIN_ENTITY_FK'],
        ['profile_type_domain_map', 'domain_id', 'DOMAIN_ENTITY_FK'],

        // adjudication status
        ['calendar_event_knowledge', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['calendar_events', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['calendar_events_backup_pre_repair', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['characters', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['dreams', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['entities', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_event', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_global', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_legacy', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['events', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['narrative_theme_observations', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['places', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['prose_annotation_spans', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['prose_drafts', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['prose_projections', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['relationships', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['semantic_aliases', 'adjudication_status_classval_id', 'CLASSVAL'],

// contradiction state
        ['calendar_event_knowledge', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['calendar_events', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['calendar_events_backup_pre_repair', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['dreams', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_event', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_global', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_legacy', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['narrative_theme_observations', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['prose_drafts', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['prose_projections', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['relationships', 'contradiction_state_classval_id', 'CLASSVAL'],

// epistemic origin
        ['calendar_event_knowledge', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['calendar_events', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['calendar_events_backup_pre_repair', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['characters', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['dreams', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['entities', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_event', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_global', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['entity_linked_facts_legacy', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['events', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['narrative_theme_observations', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['places', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['prose_annotation_spans', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['prose_drafts', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['prose_projections', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['relationships', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['semantic_aliases', 'epistemic_origin_classval_id', 'CLASSVAL'],

// authority level
        ['prose_annotation_spans', 'authority_level_classval_id', 'CLASSVAL'],

// semantic surface evidence
        ['semantic_surface_evidence', 'confidence_classval_id', 'CLASSVAL'],
        ['semantic_surface_evidence', 'resolution_method_classval_id', 'CLASSVAL'],
        ['semantic_surface_evidence', 'resolution_status_classval_id', 'CLASSVAL'],
        ['semantic_surface_evidence', 'source_classval_id', 'CLASSVAL'],
        ['semantic_surface_evidence_candidates', 'resolution_method_classval_id', 'CLASSVAL'],

        // classval references
        ['attribute_type_layer_map', 'layer_classval_id', 'CLASSVAL'],
        ['character_profile_attributes', 'value_classval_id', 'CLASSVAL'],
        ['choreography_hierarchy', 'child_group_classval_id', 'CLASSVAL'],
        ['dancer_choreography_status', 'status_classval_id', 'CLASSVAL'],
        ['figure_realizations', 'realization_classval_id', 'CLASSVAL'],
        ['entity_linked_fact_qualifiers', 'value_classval_id', 'CLASSVAL'],
        ['expression_constraint_outputs', 'access_state_classval_id', 'CLASSVAL'],
        ['expression_constraint_outputs', 'constraint_effect_type_classval_id', 'CLASSVAL'],
        ['expression_constraint_outputs', 'constraint_strength_classval_id', 'CLASSVAL'],
        ['expression_constraint_outputs', 'input_value_classval_id', 'CLASSVAL'],
        ['expression_constraint_outputs', 'output_value_classval_id', 'CLASSVAL'],
        ['expression_constraint_rule_conditions', 'comparator_classval_id', 'CLASSVAL'],
        ['expression_constraint_rule_conditions', 'expected_value_classval_id', 'CLASSVAL'],
        ['expression_constraint_rule_effects', 'access_state_classval_id', 'CLASSVAL'],
        ['expression_constraint_rule_effects', 'constraint_effect_type_classval_id', 'CLASSVAL'],
        ['expression_constraint_rule_effects', 'output_value_classval_id', 'CLASSVAL'],
        ['expression_constraint_rules', 'constraint_rule_type_classval_id', 'CLASSVAL'],
        ['expression_constraint_rules', 'profile_type_classval_id', 'CLASSVAL'],
        ['expression_constraint_rules', 'strength_classval_id', 'CLASSVAL'],

        ['expression_theme_inference_rules', 'confidence_classval_id', 'CLASSVAL'],
        ['expression_theme_inference_rules', 'output_value_classval_id', 'CLASSVAL'],

        ['identity_context_alias_map', 'alias_type_classval_id', 'CLASSVAL'],
        ['nl_intent_directives', 'intent_classval_id', 'CLASSVAL'],
        ['nl_intent_traversals', 'intent_classval_id', 'CLASSVAL'],
        ['nl_phrase_patterns', 'intent_classval_id', 'CLASSVAL'],
        ['performance_routines', 'status_classval_id', 'CLASSVAL'],
        ['prose_projections', 'projection_classval_id', 'CLASSVAL'],
        ['relationships', 'status_classval_id', 'CLASSVAL'],
        ['segment_groups', 'group_classval_id', 'CLASSVAL'],
        ['segment_pairings', 'status_classval_id', 'CLASSVAL'],
        ['team_choreography_assignments', 'status_classval_id', 'CLASSVAL'],
        ['team_choreography_status', 'status_classval_id', 'CLASSVAL'],
        ['teams', 'team_domain_classval_id', 'CLASSVAL'],

        // governed fact view-projected classval references
        ['accepted_canonical_entity_linked_facts_event', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['accepted_canonical_entity_linked_facts_event', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['accepted_canonical_entity_linked_facts_event', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['accepted_canonical_entity_linked_facts_global', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['accepted_canonical_entity_linked_facts_global', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['accepted_canonical_entity_linked_facts_global', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['canonical_entity_linked_facts_event', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['canonical_entity_linked_facts_event', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['canonical_entity_linked_facts_event', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['canonical_entity_linked_facts_global', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['canonical_entity_linked_facts_global', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['canonical_entity_linked_facts_global', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['canonical_unresolved_contradictions_global', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['canonical_unresolved_contradictions_global', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['canonical_unresolved_contradictions_global', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['governed_canonical_entity_linked_facts_event', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['governed_canonical_entity_linked_facts_event', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['governed_canonical_entity_linked_facts_event', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['governed_canonical_entity_linked_facts_global', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['governed_canonical_entity_linked_facts_global', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['governed_canonical_entity_linked_facts_global', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['canonical_unresolved_contradictions_event', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['canonical_unresolved_contradictions_event', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['canonical_unresolved_contradictions_event', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['unresolved_entity_fact_contradictions_event', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['unresolved_entity_fact_contradictions_event', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['unresolved_entity_fact_contradictions_event', 'epistemic_origin_classval_id', 'CLASSVAL'],

        ['unresolved_entity_fact_contradictions_global', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['unresolved_entity_fact_contradictions_global', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['unresolved_entity_fact_contradictions_global', 'epistemic_origin_classval_id', 'CLASSVAL'],

        // view-projected classval references
        ['v_character_appearance_resolved', 'value_classval_id', 'CLASSVAL'],
        ['v_medley_2025_display', 'group_classval_id', 'CLASSVAL'],
        ['v_medley_2025_display', 'status_classval_id', 'CLASSVAL'],
        ['v_medley_2025_v1_final', 'group_classval_id', 'CLASSVAL'],
        ['v_medley_2025_v1_final', 'status_classval_id', 'CLASSVAL'],
        ['v_medley_pairings', 'group_classval_id', 'CLASSVAL'],
        ['v_medley_pairings', 'status_classval_id', 'CLASSVAL'],
        ['v_relationship_resolver', 'status_classval_id', 'CLASSVAL'],

        /*
         * Figure choreography projections expose:
         *
         *     figures.classval_id
         *
         * as legacy semantic choreography labels.
         *
         * These are NOT canonical ontology CLASSVAL references.
         *
         * Canonical choreography authority now lives in:
         *
         *     figures.id
         *     figure_concepts
         *     figure_realizations
         *
         * Therefore these projections are intentionally excluded
         * from CLASSVAL identity enforcement.
         */
    ];

    $violations = [];
    $queryErrors = [];
    $classifiedReferences = [];

    $intentionallyUnclassifiedReferences = [
        'vw_figure_following_conditions.following_figure_classval_id' => true,
        'vw_figure_following_conditions.predecessor_figure_classval_id' => true,
    ];

        foreach ($references as [$tableName, $columnName, $expectedKind]) {
            $classifiedReferences[$tableName . '.' . $columnName] = true;
        }
    $deprecatedBeatDomainMapTable = 'calendar_beat_' . 'domain_map';

    $discoverySql = "
        SELECT TABLE_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = " . $pdo->quote($schemaName) . "
          AND TABLE_NAME <> " . $pdo->quote($deprecatedBeatDomainMapTable) . "
          AND (
              COLUMN_NAME LIKE '%\\_classval_id'
              OR COLUMN_NAME LIKE '%\\_domain_id'
              OR COLUMN_NAME = 'domain_id'
          )
        ORDER BY TABLE_NAME, COLUMN_NAME
    ";

        $stmt = $pdo->query($discoverySql);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'];

        if (
            isset($classifiedReferences[$key])
            || isset($intentionallyUnclassifiedReferences[$key])
        ) {
            continue;
        }

        $quotedSchema = '`' . str_replace('`', '``', $schemaName) . '`';
        $quotedTable = '`' . str_replace('`', '``', $row['TABLE_NAME']) . '`';
        $quotedColumn = '`' . str_replace('`', '``', $row['COLUMN_NAME']) . '`';

        $referenceCountSql = "
        SELECT
            COUNT(*) AS reference_count,
            MIN($quotedColumn) AS invalid_value
        FROM {$quotedSchema}.{$quotedTable}
        WHERE {$quotedColumn} IS NOT NULL
          AND {$quotedColumn} <> ''
    ";

        try {
            $countStmt = $pdo->query($referenceCountSql);

            $summary = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $referenceCount = (int) ($summary['reference_count'] ?? 0);

            /*
             * Zero references
             * !=
             * invalid classification.
             *
             * Only fail when actual reference values exist.
             */
            if ($referenceCount <= 0) {
                continue;
            }

            $violations[] = [
                'table_name' => $row['TABLE_NAME'],
                'column_name' => $row['COLUMN_NAME'],
                'expected_kind' => 'UNCLASSIFIED_REFERENCE',
                'invalid_value' => $summary['invalid_value'] ?? null,
                'actual_entity_type_id' => null,
                'reference_count' => $referenceCount,
            ];
        } catch (Throwable $e) {
            $queryErrors[] = [
                'table_name' => $row['TABLE_NAME'],
                'column_name' => $row['COLUMN_NAME'],
                'expected_kind' => 'UNCLASSIFIED_REFERENCE',
                'error' => $e->getMessage(),
            ];
        }
    }

    foreach ($references as [$tableName, $columnName, $expectedKind]) {
        if ($expectedKind === 'DOMAIN_ENTITY' || $expectedKind === 'DOMAIN_ENTITY_FK') {
            $sql = "
                SELECT
                    '$tableName' AS table_name,
                    '$columnName' AS column_name,
                    '$expectedKind' AS expected_kind,
                    t.$columnName AS invalid_value,
                    e.entity_type_id AS actual_entity_type_id,
                    COUNT(*) AS reference_count
                FROM $schemaName.$tableName t
                LEFT JOIN $schemaName.entities e
                    ON e.id COLLATE utf8mb4_general_ci = t.$columnName COLLATE utf8mb4_general_ci
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
        } elseif ($expectedKind === 'DOMAIN_CLASSVAL') {
            $sql = "
                SELECT
                    '{$tableName}' AS table_name,
                    '{$columnName}' AS column_name,
                    '{$expectedKind}' AS expected_kind,
                    t.{$columnName} AS invalid_value,
                    NULL AS actual_entity_type_id,
                    COUNT(*) AS reference_count
                FROM {$schemaName}.{$tableName} t
                LEFT JOIN {$schemaName}.calendar_domain_classvals cd
                    ON cd.id COLLATE utf8mb4_general_ci = t.{$columnName} COLLATE utf8mb4_general_ci
                WHERE t.{$columnName} IS NOT NULL
                  AND t.{$columnName} <> ''
                  AND cd.id IS NULL
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
