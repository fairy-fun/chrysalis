<?php
declare(strict_types=1);

function fw_start_workflow(
    PDO $pdo,
    string $workflowId
): array {

    $workflow = fw_get_workflow_definition($workflowId);

    $initialState = $workflow['initial_state'] ?? null;

    if (!is_string($initialState) || $initialState === '') {

        throw new RuntimeException(
            "Workflow has no initial_state: {$workflowId}"
        );
    }

    return fw_run_workflow_state(
        $pdo,
        $workflowId,
        $initialState
    );
}

function fw_resume_workflow(
    PDO $pdo,
    string $workflowId,
    string $stateName,
    array $input = [],
    array $context = [],
    array $snapshots = []
): array {

    return fw_run_workflow_state(
        $pdo,
        $workflowId,
        $stateName,
        $input,
        $context,
        $snapshots
    );
}

function fw_run_workflow_state(
    PDO $pdo,
    string $workflowId,
    string $stateName,
    array $input = [],
    array $context = [],
    array $snapshots = []
): array {

    $workflow = fw_get_workflow_definition($workflowId);

    $state = fw_get_workflow_state(
        $workflow,
        $stateName
    );

    $type = $state['type'] ?? null;

    switch ($type) {

        case 'input':

            return fw_handle_workflow_input_state(
                $pdo,
                $workflow,
                $stateName,
                $state,
                $input,
                $context,
                $snapshots
            );

        case 'action':

            return fw_workflow_execute_action_and_continue(
                $pdo,
                $workflow,
                $stateName,
                $state,
                $input,
                $context,
                $snapshots
            );

        case 'terminal':

            return fw_workflow_terminal_response(
                $workflow,
                $stateName,
                $state,
                $input,
                $context,
                $snapshots
            );
    }

    throw new RuntimeException(
        "Unsupported workflow state type: {$type}"
    );
}

function fw_get_workflow_definition(
    string $workflowId
): array {

    $registry = $GLOBALS['fw_workflow_registry'] ?? [];

    if (
        !isset($registry[$workflowId]) ||
        !is_array($registry[$workflowId])
    ) {

        throw new RuntimeException(
            "Unknown workflow_id: {$workflowId}"
        );
    }

    return $registry[$workflowId];
}

function fw_get_workflow_state(
    array $workflow,
    string $stateName
): array {

    if (
        !isset($workflow['states'][$stateName]) ||
        !is_array($workflow['states'][$stateName])
    ) {

        throw new RuntimeException(
            "Unknown workflow state: {$stateName}"
        );
    }

    return $workflow['states'][$stateName];
}

function fw_workflow_execute_action_and_continue(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $input,
    array $context,
    array $snapshots
): array {

    $result = fw_workflow_execute_action(
        $pdo,
        $state,
        $input,
        $context
    );

    $success = $result['success'] ?? false;

    $newContext = $result['context'] ?? $context;

    $nextState = fw_resolve_workflow_transition(
        $state['transition'] ?? [],
        $success,
        $input,
        $newContext
    );

    $snapshots[] = [

        'workflow_id' => $workflow['workflow_id'],

        'from_state' => $stateName,

        'to_state' => $nextState,

        'transition_reason' =>
            $result['transition_reason'] ?? null,

        'validated_data' =>
            $result['validated_data'] ?? [],
    ];

    return fw_run_workflow_state(
        $pdo,
        $workflow['workflow_id'],
        $nextState,
        $input,
        $newContext,
        $snapshots
    );
}

function fw_workflow_execute_action(
    PDO $pdo,
    array $state,
    array $input,
    array $context
): array {

    if (isset($state['assert'])) {

        $passes = fw_evaluate_workflow_assertion(
            $state['assert'],
            $input,
            $context
        );

        return [

            'success' => $passes,

            'context' => $context,

            'transition_reason' => $passes
                ? 'assertion_passed'
                : 'assertion_failed',
        ];
    }

    $action = $state['action'] ?? null;

    if (!is_array($action)) {

        throw new RuntimeException(
            'Action state missing action definition.'
        );
    }

    $driver = $action['driver'] ?? null;

    if ($driver === 'db') {

        return fw_execute_workflow_db_select_one(
            $pdo,
            $action,
            $input,
            $context
        );
    }

    return fw_execute_workflow_driver_operation(
        $pdo,
        $action,
        $input,
        $context
    );
}

function fw_workflow_terminal_response(
    array $workflow,
    string $stateName,
    array $state,
    array $input,
    array $context,
    array $snapshots
): array {

    $response = [

        'status' => isset($state['postman_payload'])
            ? 'ready_to_execute'
            : 'terminal',

        'workflow_id' => $workflow['workflow_id'],

        'state_id' => $stateName,

        'message' => $state['message'] ?? null,

        'next_required_file' =>
            $state['next_required_file'] ?? null,

        'context' => $context,

        'snapshots' => $snapshots,
    ];

    if (isset($state['postman_payload'])) {

        $response['postman_payload'] =
            fw_resolve_workflow_value(
                $state['postman_payload'],
                $input,
                $context
            );
    }

    return $response;
}