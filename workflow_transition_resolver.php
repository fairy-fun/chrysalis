<?php
declare(strict_types=1);

function fw_resolve_workflow_transition(
    array $state,
    bool $success,
    array $input = [],
    array $context = []
): string {

    if ($success) {

        $next = $state['next'] ?? null;

        if (!is_string($next) || trim($next) === '') {
            throw new RuntimeException(
                'Missing next state.'
            );
        }

        return $next;
    }

    $failure = $state['failure_state'] ?? null;

    if (!is_string($failure) || trim($failure) === '') {
        throw new RuntimeException(
            'Missing failure_state.'
        );
    }

    return $failure;
}