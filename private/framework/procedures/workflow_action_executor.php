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

        $sql = $action['sql'];

        $bindings = [];

        foreach (($action['bindings'] ?? []) as $key => $value) {

            if ($value === '$input.entity_id') {
                $bindings[$key] = $input['entity_id'] ?? null;
            }
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute($bindings);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {

            $failureState =
                $state['failure_state'] ?? null;

            return [
                'workflow_id' => $workflow['workflow_id'],
                'state' => $failureState,
                'type' => 'terminal',
                'message' => 'Validation failed.',
            ];
        }

        $nextState = $state['next'] ?? null;

        return fw_run_workflow_state(
            $pdo,
            $workflow['workflow_id'],
            $nextState,
            $input,
            array_merge(
                $context,
                ['row' => $row]
            )
        );
    }

    throw new RuntimeException(
        "Unsupported action driver/operation combination."
    );
}