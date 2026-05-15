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
        if (!$passes) {
            return fw_run_workflow_state(
                $pdo,
                $workflow['workflow_id'],
                $state['failure_state'],
                $input,
                $context
            );
        }

        return fw_run_workflow_state(
            $pdo,
            $workflow['workflow_id'],
            $state['next'],
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

        $sql = $action['sql'];

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
            return fw_run_workflow_state(
                $pdo,
                $workflow['workflow_id'],
                $state['failure_state'],
                $input,
                $context
            );
        }

        return fw_run_workflow_state(
            $pdo,
            $workflow['workflow_id'],
            $state['next'],
            $input,
            array_merge(
                $context,
                ['row' => $row]
            )
        );
    }

    throw new RuntimeException(
        'Unsupported action driver/operation combination.'
    );
}