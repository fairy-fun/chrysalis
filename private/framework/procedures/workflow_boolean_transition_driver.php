<?php
declare(strict_types=1);

function fw_resolve_boolean_workflow_transition(
    array $transition,
    bool $success,
    array $input = [],
    array $context = []
): ?string {

    $definition = $transition['transition'] ?? $transition;

    if ($success) {
        return $definition['next'] ?? null;
    }

    return $definition['failure_state'] ?? null;
}