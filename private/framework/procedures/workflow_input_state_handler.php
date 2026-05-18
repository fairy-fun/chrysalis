<?php
declare(strict_types=1);

function fw_handle_workflow_input_state(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $input = [],
    array $context = []
): array {

    return [
        'workflow_id' => $workflow['workflow_id'],
        'state' => $stateName,
        'type' => 'input',
        'prompt' => $state['prompt'],
        'expected_input' => $state['expected_input'],
        'next' => $state['next'] ?? null,
        'context' => $context,
    ];
}