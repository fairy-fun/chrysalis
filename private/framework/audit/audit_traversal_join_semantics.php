<?php


declare(strict_types=1);

function audit_traversal_join_semantics(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $stmt = $pdo->query(<<<'SQL'
SELECT
    traversal_path_id,
    sequence_index,
    left_table_name,
    via_table,
    from_column,
    to_column,
    is_optional
FROM sxnzlfun_chrysalis.entity_traversal_steps
ORDER BY traversal_path_id, sequence_index
SQL
    );

    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stepsByPath = audit_traversal_join_semantics_group_by_path($steps);

    foreach ($stepsByPath as $pathId => $pathSteps) {
        audit_traversal_join_semantics_path(
            $pdo,
            (string)$pathId,
            $pathSteps,
            $violations
        );
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

function audit_traversal_join_semantics_path(
    PDO    $pdo,
    string $pathId,
    array  $steps,
    array  &$violations
): void
{
    $seenForwardEdges = [];
    $seenReverseEdges = [];
    $optionalTables = [];

    foreach ($steps as $step) {
        $sequenceIndex = (int)($step['sequence_index'] ?? 0);

        $leftTable = audit_traversal_join_semantics_value($step['left_table_name'] ?? null);
        $viaTable = audit_traversal_join_semantics_value($step['via_table'] ?? null);
        $fromColumn = audit_traversal_join_semantics_value($step['from_column'] ?? null);
        $toColumn = audit_traversal_join_semantics_value($step['to_column'] ?? null);
        $isOptional = (int)($step['is_optional'] ?? 0) === 1;

        if ($leftTable === '' || $viaTable === '' || $fromColumn === '' || $toColumn === '') {
            continue;
        }

        $forwardEdge = implode('|', [
            $leftTable,
            $fromColumn,
            $viaTable,
            $toColumn,
        ]);

        $reverseEdge = implode('|', [
            $viaTable,
            $toColumn,
            $leftTable,
            $fromColumn,
        ]);

        if (isset($seenForwardEdges[$forwardEdge])) {
            $violations[] = [
                'violation_type' => 'duplicate_join_edge',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
                'previous_sequence_index' => $seenForwardEdges[$forwardEdge],
                'left_table_name' => $leftTable,
                'from_column' => $fromColumn,
                'via_table' => $viaTable,
                'to_column' => $toColumn,
            ];
        }

        if (isset($seenReverseEdges[$forwardEdge])) {
            $violations[] = [
                'violation_type' => 'reversed_join_edge',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
                'previous_sequence_index' => $seenReverseEdges[$forwardEdge],
                'left_table_name' => $leftTable,
                'from_column' => $fromColumn,
                'via_table' => $viaTable,
                'to_column' => $toColumn,
            ];
        }

        $seenForwardEdges[$forwardEdge] = $sequenceIndex;
        $seenReverseEdges[$reverseEdge] = $sequenceIndex;

        if (isset($optionalTables[$leftTable]) && !$isOptional) {
            $violations[] = [
                'violation_type' => 'required_join_after_optional_table',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
                'left_table_name' => $leftTable,
                'left_table_first_optional_sequence_index' => $optionalTables[$leftTable],
            ];
        }

        if ($isOptional) {
            $optionalTables[$viaTable] = $sequenceIndex;
        }

        $fromIsUnique = audit_traversal_join_semantics_column_is_unique(
            $pdo,
            $leftTable,
            $fromColumn
        );

        $toIsUnique = audit_traversal_join_semantics_column_is_unique(
            $pdo,
            $viaTable,
            $toColumn
        );

        if (!$fromIsUnique && !$toIsUnique) {
            $violations[] = [
                'violation_type' => 'possible_many_to_many_join',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
                'left_table_name' => $leftTable,
                'from_column' => $fromColumn,
                'via_table' => $viaTable,
                'to_column' => $toColumn,
            ];
        }
    }
}

function audit_traversal_join_semantics_column_is_unique(
    PDO    $pdo,
    string $tableName,
    string $columnName
): bool
{
    static $cache = [];

    $cacheKey = $tableName . '.' . $columnName;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare(<<<'SQL'
SELECT 1
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = :table_name
  AND COLUMN_NAME = :column_name
  AND NON_UNIQUE = 0
LIMIT 1
SQL
    );

    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    $cache[$cacheKey] = (bool)$stmt->fetchColumn();

    return $cache[$cacheKey];
}

function audit_traversal_join_semantics_group_by_path(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $pathId = (string)$row['traversal_path_id'];
        $grouped[$pathId][] = $row;
    }

    return $grouped;
}

function audit_traversal_join_semantics_value(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function assert_traversal_join_semantics(PDO $pdo, string $schemaName): void
{
    $audit = audit_traversal_join_semantics($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Traversal join semantics failed: '
        . json_encode($audit['violations'], JSON_UNESCAPED_SLASHES)
    );
}