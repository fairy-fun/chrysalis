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
    throw new RuntimeException(
        "Workflow action execution not implemented yet for state: {$stateName}"
    );
}