<?php
declare(strict_types=1);

function fw_resolve_match_workflow_transition(
    array $transition,
    bool $success,
    array $input = [],
    array $context = []
): ?string {

    $definition = $transition['transition'] ?? $transition;

    $value = fw_resolve_workflow_value(
        $definition['value'] ?? null,
        $input,
        $context
    );

    $cases = $definition['cases'] ?? [];

    if (array_key_exists((string) $value, $cases)) {
        return $cases[(string) $value];
    }

    return $definition['default'] ?? null;
}