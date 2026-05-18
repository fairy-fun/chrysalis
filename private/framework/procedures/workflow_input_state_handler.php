<?php
declare(strict_types=1);

function fw_handle_workflow_input_state(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $input = [],
    array $context = [],
    array $snapshots = []
): array {

    $expectedInput = $state['expected_input'] ?? null;

    if (!is_string($expectedInput) || $expectedInput === '') {

        throw new RuntimeException(
            "Input state missing expected_input: {$stateName}"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Await user input
    |--------------------------------------------------------------------------
    */

    if (!array_key_exists($expectedInput, $input)) {

        return [

            'status' => 'awaiting_input',

            'workflow_id' => $workflow['workflow_id'],

            'state_id' => $stateName,

            'prompt' => $state['prompt'] ?? null,

            'expected_input' => $expectedInput,

            'context' => $context,

            'snapshots' => $snapshots,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Input already supplied
    |--------------------------------------------------------------------------
    */

    $nextState = fw_resolve_workflow_transition(
        $state['transition'] ?? [],
        true,
        $input,
        $context
    );

    $snapshots[] = [

        'workflow_id' => $workflow['workflow_id'],

        'from_state' => $stateName,

        'to_state' => $nextState,

        'accepted_input' => [
            $expectedInput => $input[$expectedInput],
        ],

        'transition_reason' => 'input_received',
    ];

    return fw_run_workflow_state(
        $pdo,
        $workflow['workflow_id'],
        $nextState,
        $input,
        $context,
        $snapshots
    );
}