<?php
declare(strict_types=1);

function fw_handle_workflow_assertion_action(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $input = [],
    array $context = []
): array {

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