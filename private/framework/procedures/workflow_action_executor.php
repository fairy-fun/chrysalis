<?php
declare(strict_types=1);

function fw_execute_workflow_action_state(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $input = [],
    array $context = []
): array {

    if (isset($state['assert'])) {

        $passes = fw_evaluate_workflow_assertion(
            $state['assert'],
            $input,
            $context
        );

        $nextState = fw_resolve_workflow_transition(
            $state,
            $passes,
            $input,
            $context
        );

        return fw_run_workflow_state(
            $pdo,
            $workflow['workflow_id'],
            $nextState,
            $input,
            $context
        );
    }

    $action = $state['action'] ?? null;

    if (!$action) {
        throw new RuntimeException(
            "Missing action definition for state: {$stateName}"
        );
    }

    $result = fw_execute_workflow_driver_operation(
        $pdo,
        $action,
        $input,
        $context
    );

    $success = $result['success'] ?? false;

    $nextState = fw_resolve_workflow_transition(
        $state,
        $success,
        $input,
        $context
    );

    return fw_run_workflow_state(
        $pdo,
        $workflow['workflow_id'],
        $nextState,
        $input,
        $result['context'] ?? $context
    );
}