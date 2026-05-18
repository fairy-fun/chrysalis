<?php
declare(strict_types=1);

function fw_dispatch_workflow_state(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $input = [],
    array $context = []
): array {

    $type = $state['type'] ?? null;

    $map = [

        'input' => 'fw_handle_workflow_input_state',
        'terminal' => 'fw_handle_workflow_terminal_state',
        'action' => 'fw_execute_workflow_action_state',

    ];

    $handler = $map[$type] ?? null;

    if (!is_string($handler) || !function_exists($handler)) {

        throw new RuntimeException(
            "Unsupported workflow state type: {$type}"
        );
    }

    return $handler(
        $pdo,
        $workflow,
        $stateName,
        $state,
        $input,
        $context
    );
}