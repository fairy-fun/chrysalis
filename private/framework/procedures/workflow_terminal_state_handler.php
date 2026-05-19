<?php
declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';

function fw_handle_workflow_terminal_state(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $input = [],
    array $context = []
): array {

    $response = null;

    if (array_key_exists('response', $state)) {

        $response = fw_resolve_workflow_value(
            $state['response'],
            $input,
            $context
        );
    }

    return [
        'workflow_id' => $workflow['workflow_id'],
        'state' => $stateName,
        'type' => 'terminal',
        'message' => $state['message'] ?? null,
        'response' => $response,
    ];
}