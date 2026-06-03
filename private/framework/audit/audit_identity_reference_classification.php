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
        ['v_medley_2025_display', 'group_classval_id', 'CLASSVAL'],
        ['v_medley_2025_display', 'status_classval_id', 'CLASSVAL'],
        ['v_medley_2025_v1_final', 'group_classval_id', 'CLASSVAL'],
        ['v_medley_2025_v1_final', 'status_classval_id', 'CLASSVAL'],
        ['v_medley_pairings', 'group_classval_id', 'CLASSVAL'],
        ['v_medley_pairings', 'status_classval_id', 'CLASSVAL'],
        ['v_character_relationship_resolved', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['v_relationship_fact_resolved', 'adjudication_status_classval_id', 'CLASSVAL'],
        ['v_relationship_fact_resolved', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['v_relationship_fact_resolved', 'epistemic_origin_classval_id', 'CLASSVAL'],
        ['v_relationship_resolved', 'contradiction_state_classval_id', 'CLASSVAL'],
        ['v_relationship_resolved', 'status_classval_id', 'CLASSVAL'],
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