<?php

declare(strict_types=1);

function audit_traversal_projection_integrity(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $pathStmt = $pdo->query(<<<'SQL'
SELECT
    p.id AS traversal_path_id,
    t.root_entity_type_id,
    etc.base_table_name AS root_table_name
FROM sxnzlfun_chrysalis.entity_traversal_paths p
JOIN sxnzlfun_chrysalis.entity_traversals t
  ON t.id = p.traversal_id
JOIN sxnzlfun_chrysalis.entity_type_classvals etc
  ON etc.id = t.root_entity_type_id
ORDER BY p.id
SQL
    );

    $paths = $pathStmt->fetchAll(PDO::FETCH_ASSOC);

    $stepStmt = $pdo->query(<<<'SQL'
SELECT
    traversal_path_id,
    sequence_index,
    left_table_name,
    via_table
FROM sxnzlfun_chrysalis.entity_traversal_steps
ORDER BY traversal_path_id, sequence_index
SQL
    );

    $projectionStmt = $pdo->query(<<<'SQL'
SELECT
    traversal_path_id,
    sequence_index,
    source_table_name,
    source_column_name,
    output_name
FROM sxnzlfun_chrysalis.entity_traversal_projections
ORDER BY traversal_path_id, sequence_index
SQL
    );

    $orderingStmt = $pdo->query(<<<'SQL'
SELECT
    traversal_path_id,
    sequence_index,
    source_table_name,
    column_name
FROM sxnzlfun_chrysalis.entity_traversal_ordering
ORDER BY traversal_path_id, sequence_index
SQL
    );

    $stepsByPath = audit_traversal_projection_group_by_path(
        $stepStmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $projectionsByPath = audit_traversal_projection_group_by_path(
        $projectionStmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $orderingByPath = audit_traversal_projection_group_by_path(
        $orderingStmt->fetchAll(PDO::FETCH_ASSOC)
    );

    foreach ($paths as $path) {
        $pathId = (string)$path['traversal_path_id'];
        $rootTable = audit_traversal_projection_value($path['root_table_name'] ?? null);

        if ($rootTable === '') {
            $violations[] = [
                'violation_type' => 'missing_root_table',
                'traversal_path_id' => $pathId,
            ];
            continue;
        }

        $reachableTables = [$rootTable => true];

        foreach ($stepsByPath[$pathId] ?? [] as $step) {
            $leftTable = audit_traversal_projection_value($step['left_table_name'] ?? null);
            $viaTable = audit_traversal_projection_value($step['via_table'] ?? null);
            $sequenceIndex = (int)($step['sequence_index'] ?? 0);

            if ($leftTable === '' || $viaTable === '') {
                continue;
            }

            if (!isset($reachableTables[$leftTable])) {
                continue;
            }

            $reachableTables[$viaTable] = true;
        }

        $projections = $projectionsByPath[$pathId] ?? [];

        if ($projections === []) {
            $violations[] = [
                'violation_type' => 'missing_projection',
                'traversal_path_id' => $pathId,
            ];
        }

        $outputNames = [];

        foreach ($projections as $projection) {
            $sequenceIndex = (int)($projection['sequence_index'] ?? 0);
            $sourceTable = audit_traversal_projection_value($projection['source_table_name'] ?? null);
            $sourceColumn = audit_traversal_projection_value($projection['source_column_name'] ?? null);
            $outputName = audit_traversal_projection_value($projection['output_name'] ?? null);

            if ($sourceTable === '') {
                $violations[] = [
                    'violation_type' => 'missing_projection_source_table',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                ];
                continue;
            }

            if (!isset($reachableTables[$sourceTable])) {
                $violations[] = [
                    'violation_type' => 'unreachable_projection_source_table',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                    'source_table_name' => $sourceTable,
                ];
            }

            if ($sourceColumn === '') {
                $violations[] = [
                    'violation_type' => 'missing_projection_source_column',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                    'source_table_name' => $sourceTable,
                ];
            }

            if ($outputName === '') {
                $violations[] = [
                    'violation_type' => 'missing_projection_output_name',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                    'source_table_name' => $sourceTable,
                    'source_column_name' => $sourceColumn,
                ];
                continue;
            }

            if (isset($outputNames[$outputName])) {
                $violations[] = [
                    'violation_type' => 'duplicate_projection_output_name',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                    'output_name' => $outputName,
                ];
            }

            $outputNames[$outputName] = true;
        }

        foreach ($orderingByPath[$pathId] ?? [] as $ordering) {
            $sequenceIndex = (int)($ordering['sequence_index'] ?? 0);
            $sourceTable = audit_traversal_projection_value($ordering['source_table_name'] ?? null);
            $columnName = audit_traversal_projection_value($ordering['column_name'] ?? null);

            if ($sourceTable === '') {
                $violations[] = [
                    'violation_type' => 'missing_ordering_source_table',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                ];
                continue;
            }

            if (!isset($reachableTables[$sourceTable])) {
                $violations[] = [
                    'violation_type' => 'unreachable_ordering_source_table',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                    'source_table_name' => $sourceTable,
                ];
            }

            if ($columnName === '') {
                $violations[] = [
                    'violation_type' => 'missing_ordering_column',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                    'source_table_name' => $sourceTable,
                ];
            }
        }
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'checked_path_count' => count($paths),
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function audit_traversal_projection_group_by_path(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $pathId = (string)$row['traversal_path_id'];
        $grouped[$pathId][] = $row;
    }

    return $grouped;
}

function audit_traversal_projection_value(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function assert_traversal_projection_integrity(PDO $pdo, string $schemaName): void
{
    $audit = audit_traversal_projection_integrity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Traversal projection integrity failed: '
        . json_encode($audit['violations'], JSON_UNESCAPED_SLASHES)
    );
}