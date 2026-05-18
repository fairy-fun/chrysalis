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

        $assert = $state['assert'];

        $passes = fw_evaluate_workflow_assertion(
            $assert,
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

    $driver = $action['driver'] ?? null;
    $operation = $action['operation'] ?? null;

    if (
        $driver === 'db'
        && $operation === 'select_one'
    ) {

        $result = fw_execute_workflow_db_select_one(
            $pdo,
            $action,
            $input,
            $context
        );

        $success = $result['success'];

        $nextState = fw_resolve_workflow_transition(
            $state,
            $success,
            $input,
            $context
        );

        if (!$success) {

            return fw_run_workflow_state(
                $pdo,
                $workflow['workflow_id'],
                $nextState,
                $input,
                $context
            );
        }

        return fw_run_workflow_state(
            $pdo,
            $workflow['workflow_id'],
            $nextState,
            $input,
            array_merge(
                $context,
                ['row' => $result['row']]
            )
        );
    }

    throw new RuntimeException(
        'Unsupported action driver/operation combination.'
    );
}