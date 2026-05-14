<?php
declare(strict_types=1);

function fw_run_workflow_state(
    PDO $pdo,
    string $workflowId,
    string $stateName,
    array $input = [],
    array $context = []
): array {

    $registry = $GLOBALS['fw_workflow_registry'] ?? [];

    if (!isset($registry[$workflowId])) {
        throw new RuntimeException(
            "Unknown workflow_id: {$workflowId}"
        );
    }

    $workflow = $registry[$workflowId];

    if (!isset($workflow['states'][$stateName])) {
        throw new RuntimeException(
            "Unknown state: {$stateName}"
        );
    }

    $state = $workflow['states'][$stateName];

    $type = $state['type'] ?? null;

    if ($type === 'input') {

        return [
            'workflow_id' => $workflowId,
            'state' => $stateName,
            'type' => 'input',
            'prompt' => $state['prompt'],
            'expected_input' => $state['expected_input'],
            'next' => $state['next'] ?? null,
            'context' => $context,
        ];
    }

    if ($type === 'terminal') {

        return [
            'workflow_id' => $workflowId,
            'state' => $stateName,
            'type' => 'terminal',
            'message' => $state['message'] ?? null,
        ];
    }

    if ($type === 'action') {

        return fw_execute_workflow_action_state(
            $pdo,
            $workflow,
            $stateName,
            $state,
            $input,
            $context
        );
    }

    throw new RuntimeException(
        "Unsupported state type: {$type}"
    );
}