<?php

declare(strict_types=1);

function audit_traversal_step_chain_integrity(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $stmt = $pdo->prepare(<<<'SQL'
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

    $stmt->execute();
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byPath = [];

    foreach ($steps as $step) {
        $pathId = (string)$step['traversal_path_id'];
        $byPath[$pathId][] = $step;
    }

    foreach ($byPath as $pathId => $pathSteps) {
        audit_traversal_step_chain_path(
            $pathId,
            $pathSteps,
            $violations
        );
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'checked_path_count' => count($byPath),
        'checked_step_count' => count($steps),
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function audit_traversal_step_chain_path(
    string $pathId,
    array $steps,
    array &$violations
): void {
    $sequenceIndexes = [];
    $joinSignatures = [];
    $reachableTables = [];
    $graph = [];
    $lastSequenceIndex = 0;

    foreach ($steps as $step) {
        $sequenceIndex = (int)$step['sequence_index'];

        $leftTable = audit_traversal_step_chain_value(
            $step['left_table_name'] ?? null
        );
        $viaTable = audit_traversal_step_chain_value(
            $step['via_table'] ?? null
        );
        $fromColumn = audit_traversal_step_chain_value(
            $step['from_column'] ?? null
        );
        $toColumn = audit_traversal_step_chain_value(
            $step['to_column'] ?? null
        );

        if ($sequenceIndex < 1) {
            $violations[] = [
                'violation_type' => 'invalid_sequence_index',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
            ];
            continue;
        }

        if (isset($sequenceIndexes[$sequenceIndex])) {
            $violations[] = [
                'violation_type' => 'duplicate_sequence_index',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
            ];
        }

        $sequenceIndexes[$sequenceIndex] = true;

        if ($sequenceIndex <= $lastSequenceIndex) {
            $violations[] = [
                'violation_type' => 'non_increasing_sequence_index',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
                'previous_sequence_index' => $lastSequenceIndex,
            ];
        }

        if ($sequenceIndex !== $lastSequenceIndex + 1) {
            $violations[] = [
                'violation_type' => 'non_contiguous_sequence_index',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
                'expected_sequence_index' => $lastSequenceIndex + 1,
            ];
        }

        $lastSequenceIndex = max($lastSequenceIndex, $sequenceIndex);

        foreach (
            [
                'left_table_name' => $leftTable,
                'via_table' => $viaTable,
                'from_column' => $fromColumn,
                'to_column' => $toColumn,
            ] as $field => $value
        ) {
            if ($value === '') {
                $violations[] = [
                    'violation_type' => 'missing_step_field',
                    'traversal_path_id' => $pathId,
                    'sequence_index' => $sequenceIndex,
                    'field_name' => $field,
                ];
            }
        }

        if ($leftTable === '' || $viaTable === '' || $fromColumn === '' || $toColumn === '') {
            continue;
        }

        if ($sequenceIndex === 1) {
            $reachableTables[$leftTable] = true;
        } elseif (!isset($reachableTables[$leftTable])) {
            $violations[] = [
                'violation_type' => 'unreachable_left_table',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
                'left_table_name' => $leftTable,
            ];
        }

        $joinSignature = implode('|', [
            $leftTable,
            $viaTable,
            $fromColumn,
            $toColumn,
        ]);

        if (isset($joinSignatures[$joinSignature])) {
            $violations[] = [
                'violation_type' => 'duplicate_join_signature',
                'traversal_path_id' => $pathId,
                'sequence_index' => $sequenceIndex,
                'join_signature' => $joinSignature,
            ];
        }

        $joinSignatures[$joinSignature] = true;

        $graph[$leftTable] ??= [];
        $graph[$leftTable][] = $viaTable;
        $graph[$viaTable] ??= [];

        $reachableTables[$viaTable] = true;
    }

    $cycle = audit_traversal_step_chain_cycle($graph);

    if ($cycle !== []) {
        $violations[] = [
            'violation_type' => 'cycle_detected',
            'traversal_path_id' => $pathId,
            'cycle_tables' => $cycle,
        ];
    }
}

function audit_traversal_step_chain_value(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function audit_traversal_step_chain_cycle(array $graph): array
{
    $visited = [];
    $stack = [];
    $path = [];

    foreach (array_keys($graph) as $node) {
        $cycle = audit_traversal_step_chain_cycle_visit(
            $node,
            $graph,
            $visited,
            $stack,
            $path
        );

        if ($cycle !== []) {
            return $cycle;
        }
    }

    return [];
}

function audit_traversal_step_chain_cycle_visit(
    string $node,
    array $graph,
    array &$visited,
    array &$stack,
    array &$path
): array {
    if (isset($stack[$node])) {
        return array_values($path);
    }

    if (isset($visited[$node])) {
        return [];
    }

    $visited[$node] = true;
    $stack[$node] = true;
    $path[] = $node;

    foreach ($graph[$node] ?? [] as $next) {
        $cycle = audit_traversal_step_chain_cycle_visit(
            (string)$next,
            $graph,
            $visited,
            $stack,
            $path
        );

        if ($cycle !== []) {
            return $cycle;
        }
    }

    array_pop($path);
    unset($stack[$node]);

    return [];
}

function assert_traversal_step_chain_integrity(PDO $pdo, string $schemaName): void
{
    $audit = audit_traversal_step_chain_integrity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Traversal step chain integrity failed: '
        . json_encode($audit['violations'], JSON_UNESCAPED_SLASHES)
    );
}