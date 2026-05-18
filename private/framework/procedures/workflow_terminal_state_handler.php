<?php
declare(strict_types=1);

function fw_handle_workflow_terminal_state(
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
        'type' => 'terminal',
        'message' => $state['message'] ?? null,
    ];
}