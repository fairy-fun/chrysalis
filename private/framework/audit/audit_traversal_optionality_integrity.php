<?php


declare(strict_types=1);

function audit_traversal_optionality_integrity(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $steps = $pdo->query(<<<'SQL'
SELECT
    traversal_path_id,
    sequence_index,
    left_table_name,
    via_table,
    is_optional
FROM sxnzlfun_chrysalis.entity_traversal_steps
ORDER BY traversal_path_id, sequence_index
SQL
    )->fetchAll(PDO::FETCH_ASSOC);

    $projections = $pdo->query(<<<'SQL'
SELECT
    traversal_path_id,
    sequence_index,
    source_table_name,
    source_column_name,
    output_name
FROM sxnzlfun_chrysalis.entity_traversal_projections
ORDER BY traversal_path_id, sequence_index
SQL
    )->fetchAll(PDO::FETCH_ASSOC);

    $ordering = $pdo->query(<<<'SQL'
SELECT
    traversal_path_id,
    sequence_index,
    source_table_name,
    column_name
FROM sxnzlfun_chrysalis.entity_traversal_ordering
ORDER BY traversal_path_id, sequence_index
SQL
    )->fetchAll(PDO::FETCH_ASSOC);

    $stepsByPath = audit_traversal_optionality_group_by_path($steps);
    $projectionsByPath = audit_traversal_optionality_group_by_path($projections);
    $orderingByPath = audit_traversal_optionality_group_by_path($ordering);

    foreach ($stepsByPath as $pathId => $pathSteps) {
        $optionalTables = [];

        foreach ($pathSteps as $step) {
            $sequenceIndex = (int)($step['sequence_index'] ?? 0);
            $leftTable = audit_traversal_optionality_value($step['left_table_name'] ?? null);
            $viaTable = audit_traversal_optionality_value($step['via_table'] ?? null);
            $isOptional = (int)($step['is_optional'] ?? 0) === 1;

            if ($leftTable === '' || $viaTable === '') {
                continue;
            }

            if (isset($optionalTables[$leftTable]) && !$isOptional) {
                $violations[] = [
                    'violation_type' => 'required_step_depends_on_optional_table',
                    'traversal_path_id' => (string)$pathId,
                    'sequence_index' => $sequenceIndex,
                    'left_table_name' => $leftTable,
                    'optional_source_sequence_index' => $optionalTables[$leftTable],
                ];
            }

            if ($isOptional) {
                $optionalTables[$viaTable] = $sequenceIndex;
            }

            if (isset($optionalTables[$leftTable])) {
                $optionalTables[$viaTable] = $optionalTables[$leftTable];
            }
        }

        foreach ($projectionsByPath[(string)$pathId] ?? [] as $projection) {
            $sequenceIndex = (int)($projection['sequence_index'] ?? 0);
            $sourceTable = audit_traversal_optionality_value($projection['source_table_name'] ?? null);

            if ($sourceTable !== '' && isset($optionalTables[$sourceTable])) {
                $violations[] = [
                    'violation_type' => 'projection_depends_on_optional_table',
                    'traversal_path_id' => (string)$pathId,
                    'sequence_index' => $sequenceIndex,
                    'source_table_name' => $sourceTable,
                    'source_column_name' => audit_traversal_optionality_value($projection['source_column_name'] ?? null),
                    'output_name' => audit_traversal_optionality_value($projection['output_name'] ?? null),
                    'optional_source_sequence_index' => $optionalTables[$sourceTable],
                ];
            }
        }

        foreach ($orderingByPath[(string)$pathId] ?? [] as $order) {
            $sequenceIndex = (int)($order['sequence_index'] ?? 0);
            $sourceTable = audit_traversal_optionality_value($order['source_table_name'] ?? null);

            if ($sourceTable !== '' && isset($optionalTables[$sourceTable])) {
                $violations[] = [
                    'violation_type' => 'ordering_depends_on_optional_table',
                    'traversal_path_id' => (string)$pathId,
                    'sequence_index' => $sequenceIndex,
                    'source_table_name' => $sourceTable,
                    'column_name' => audit_traversal_optionality_value($order['column_name'] ?? null),
                    'optional_source_sequence_index' => $optionalTables[$sourceTable],
                ];
            }
        }
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'checked_path_count' => count($stepsByPath),
        'checked_step_count' => count($steps),
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function audit_traversal_optionality_group_by_path(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $pathId = (string)$row['traversal_path_id'];
        $grouped[$pathId][] = $row;
    }

    return $grouped;
}

function audit_traversal_optionality_value(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function assert_traversal_optionality_integrity(PDO $pdo, string $schemaName): void
{
    $audit = audit_traversal_optionality_integrity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Traversal optionality integrity failed: '
        . json_encode($audit['violations'], JSON_UNESCAPED_SLASHES)
    );
}