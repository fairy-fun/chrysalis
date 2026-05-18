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

    return fw_dispatch_workflow_state(
        $pdo,
        $workflow,
        $stateName,
        $state,
        $input,
        $context
    );
}