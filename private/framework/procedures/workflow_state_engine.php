<?php
declare(strict_types=1);

function fw_start_workflow(
    PDO $pdo,
    string $workflowId
): array {
    $workflow = fw_get_workflow_definition($workflowId);

    $initialState = $workflow['initial_state'] ?? null;

    if (!is_string($initialState) || $initialState === '') {
        throw new RuntimeException("Workflow has no initial_state: {$workflowId}");
    }

    return fw_run_workflow_state($pdo, $workflowId, $initialState);
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
    $state = fw_get_workflow_state($workflow, $stateName);

    $type = $state['type'] ?? null;

    if ($type === 'input') {
        return fw_workflow_handle_input_state(
            $pdo,
            $workflow,
            $stateName,
            $state,
            $input,
            $context,
            $snapshots
        );
    }

    if ($type === 'terminal') {
        return fw_workflow_terminal_response(
            $workflow,
            $stateName,
            $state,
            $input,
            $context,
            $snapshots
        );
    }

    if ($type === 'action') {
        return fw_workflow_execute_action_and_continue(
            $pdo,
            $workflow,
            $stateName,
            $state,
            $input,
            $context,
            $snapshots
        );
    }

    throw new RuntimeException("Unsupported workflow state type: {$type}");
}

function fw_get_workflow_definition(string $workflowId): array
{
    $registry = $GLOBALS['fw_workflow_registry'] ?? [];

    if (!isset($registry[$workflowId]) || !is_array($registry[$workflowId])) {
        throw new RuntimeException("Unknown workflow_id: {$workflowId}");
    }

    return $registry[$workflowId];
}

function fw_get_workflow_state(array $workflow, string $stateName): array
{
    if (!isset($workflow['states'][$stateName]) || !is_array($workflow['states'][$stateName])) {
        throw new RuntimeException("Unknown workflow state: {$stateName}");
    }

    return $workflow['states'][$stateName];
}

function fw_workflow_handle_input_state(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $input,
    array $context,
    array $snapshots
): array {
    $expectedInput = $state['expected_input'] ?? null;

    if (!is_string($expectedInput) || $expectedInput === '') {
        throw new RuntimeException("Input state missing expected_input: {$stateName}");
    }

    if (!array_key_exists($expectedInput, $input)) {
        return fw_workflow_input_response(
            $workflow,
            $stateName,
            $state,
            $input,
            $context,
            $snapshots
        );
    }

    $nextState = fw_workflow_resolve_next_state(
        $state,
        true,
        $input,
        $context
    );

    $snapshots[] = [
        'workflow_id' => $workflow['workflow_id'],
        'from_state' => $stateName,
        'to_state' => $nextState,
        'accepted_input' => [
            $expectedInput => $input[$expectedInput],
        ],
        'transition_reason' => 'input_received',
    ];

    return fw_run_workflow_state(
        $pdo,
        $workflow['workflow_id'],
        $nextState,
        $input,
        $context,
        $snapshots
    );
}

function fw_workflow_input_response(
    array $workflow,
    string $stateName,
    array $state,
    array $input,
    array $context,
    array $snapshots
): array {
    return [
        'status' => 'awaiting_input',
        'workflow_id' => $workflow['workflow_id'],
        'state_id' => $stateName,
        'prompt' => $state['prompt'] ?? null,
        'expected_input' => $state['expected_input'] ?? null,
        'next_required_file' => $state['next_required_file'] ?? null,
        'context' => $context,
        'snapshots' => $snapshots,
    ];
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
        'next_required_file' => $state['next_required_file'] ?? null,
        'context' => $context,
        'snapshots' => $snapshots,
    ];

    if (isset($state['postman_payload'])) {
        $response['postman_payload'] = fw_resolve_workflow_value(
            $state['postman_payload'],
            $input,
            $context
        );
    }

    return $response;
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
    $result = fw_workflow_execute_action($pdo, $state, $input, $context);

    $success = $result['success'] ?? false;
    $newContext = $result['context'] ?? $context;

    $nextState = fw_workflow_resolve_next_state(
        $state,
        $success,
        $input,
        $newContext
    );

    $snapshots[] = [
        'workflow_id' => $workflow['workflow_id'],
        'from_state' => $stateName,
        'to_state' => $nextState,
        'transition_reason' => $result['transition_reason'] ?? null,
        'validated_data' => $result['validated_data'] ?? [],
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
        throw new RuntimeException('Action state missing action definition.');
    }

    if (($action['driver'] ?? null) === 'db') {
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

function fw_workflow_execute_db_action(
    PDO $pdo,
    array $action,
    array $input,
    array $context
): array {
    $operation = $action['operation'] ?? null;

    if ($operation !== 'select_one') {
        throw new RuntimeException("Unsupported DB workflow operation: {$operation}");
    }

    $sql = $action['sql'] ?? null;

    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('DB action missing SQL.');
    }

    $bindings = [];

    foreach (($action['bindings'] ?? []) as $key => $value) {
        $bindings[$key] = fw_resolve_workflow_value(
            $value,
            $input,
            $context
        );
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'success' => false,
            'context' => $context,
            'transition_reason' => 'row_not_found',
        ];
    }

    $store = $action['store'] ?? null;

    if (!is_string($store) || $store === '') {
        throw new RuntimeException('DB action missing store key.');
    }

    $context[$store] = $row;

    return [
        'success' => true,
        'context' => $context,
        'validated_data' => [
            $store => $row,
        ],
        'transition_reason' => 'row_found',
    ];
}

function fw_workflow_resolve_next_state(
    array $state,
    bool $success,
    array $input,
    array $context
): string {
    $transition = $state['transition'] ?? null;

    if (!is_array($transition)) {
        throw new RuntimeException('State missing transition definition.');
    }

    $driver = $transition['driver'] ?? 'boolean';

    if ($driver === 'boolean') {
        $next = $success
            ? ($transition['next'] ?? null)
            : ($transition['failure_state'] ?? null);

        if (!is_string($next) || $next === '') {
            throw new RuntimeException(
                'Boolean transition could not resolve next state.'
            );
        }

        return $next;
    }

    if ($driver === 'match') {
        $value = fw_resolve_workflow_value(
            $transition['value'] ?? null,
            $input,
            $context
        );

        $cases = $transition['cases'] ?? [];

        if (array_key_exists((string)$value, $cases)) {
            return $cases[(string)$value];
        }

        $default = $transition['default'] ?? null;

        if (!is_string($default) || $default === '') {
            throw new RuntimeException('Match transition missing default state.');
        }

        return $default;
    }

    throw new RuntimeException("Unsupported transition driver: {$driver}");
}

function fw_resolve_workflow_value(
    mixed $value,
    array $input,
    array $context
): mixed {
    if (is_array($value)) {
        $resolved = [];

        foreach ($value as $key => $item) {
            $resolved[$key] = fw_resolve_workflow_value(
                $item,
                $input,
                $context
            );
        }

        return $resolved;
    }

    if (!is_string($value)) {
        return $value;
    }

    if (str_starts_with($value, '$input.')) {
        return fw_read_workflow_path($input, substr($value, 7));
    }

    if (str_starts_with($value, '$context.')) {
        return fw_read_workflow_path($context, substr($value, 9));
    }

    return $value;
}

function fw_read_workflow_path(
    array $source,
    string $path
): mixed {
    $current = $source;

    foreach (explode('.', $path) as $part) {
        if (!is_array($current) || !array_key_exists($part, $current)) {
            return null;
        }

        $current = $current[$part];
    }

    return $current;
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