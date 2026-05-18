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

        return fw_handle_workflow_assertion_action(
            $pdo,
            $workflow,
            $stateName,
            $state,
            $input,
            $context
        );
    }

    return fw_handle_workflow_driver_action(
        $pdo,
        $workflow,
        $stateName,
        $state,
        $input,
        $context
    );
}