<?php

declare(strict_types=1);

function fw_evaluate_workflow_assertion(
    array $assert,
    array $input = [],
    array $context = []
): bool
{

    $left = fw_resolve_workflow_value(
        $assert['left'] ?? null,
        $input,
        $context
    );

    $right = fw_resolve_workflow_value(
        $assert['right'] ?? null,
        $input,
        $context
    );

    $operator = $assert['operator'] ?? null;

    return match ($operator) {

        'equals' => $left === $right,

        'not_equals' => $left !== $right,

        'is_null' => $left === null,

        'is_not_null' => $left !== null,

        'greater_than' => $left > $right,

        'greater_than_or_equal' => $left >= $right,

        'less_than' => $left < $right,

        'less_than_or_equal' => $left <= $right,

        'in' => is_array($right)
            && in_array($left, $right, true),

        'not_in' => is_array($right)
            && !in_array($left, $right, true),

        default => throw new RuntimeException(
            'Unsupported assertion operator: '
            . (string)$operator
        ),
    };
}