<?php
declare(strict_types=1);

function fw_workflow_render_input_prompt(
    PDO $pdo,
    array $workflow,
    string $stateName,
    array $state,
    array $context
): ?string {

    $prompt = $state['prompt'] ?? null;

    if (!is_string($prompt)) {
        return null;
    }

    if (
        ($workflow['workflow_id'] ?? null) !== 'calendar_book_event_create'
        || $stateName !== 'await_time_index'
    ) {
        return $prompt;
    }

    $projectionId = (int) (
        $context['calendar_normalized_input']['projection_id']
        ?? $context['projection']['id']
        ?? 0
    );

    $dayId = (int) (
        $context['book_day']['id']
        ?? 0
    );

    if ($projectionId < 1 || $dayId < 1) {
        return $prompt;
    }

    $stmt = $pdo->prepare(
        '
        SELECT
            t.time_index,
            COALESCE(
                NULLIF(TRIM(cv.label), \'\'),
                NULLIF(TRIM(t.summary), \'\'),
                NULLIF(TRIM(t.notes), \'\'),
                CONCAT(\'Time \', t.time_index)
            ) AS display_label
        FROM calendar_book_times t
        LEFT JOIN calendar_time_label_classvals cv
            ON cv.id = t.time_label_id
        WHERE t.projection_id = :projection_id
          AND t.day_id = :day_id
        ORDER BY t.time_index ASC
        '
    );

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':day_id' => $dayId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        return $prompt;
    }

    $labels = [];

    foreach ($rows as $row) {
        $timeIndex = (int) ($row['time_index'] ?? 0);

        if ($timeIndex < 1) {
            continue;
        }

        $displayLabel = trim((string) ($row['display_label'] ?? ''));

        if ($displayLabel === '') {
            $displayLabel = 'Time ' . $timeIndex;
        }

        $labels[] = $displayLabel === ('Time ' . $timeIndex)
            ? $displayLabel
            : sprintf('Time %d — %s', $timeIndex, $displayLabel);
    }

    if ($labels === []) {
        return $prompt;
    }

    return $prompt
        . "\n\nExisting time layers for this Book day:\n"
        . implode("\n", $labels)
        . "\n\nReply with the canonical slot key, such as Time 1.";
}

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

            'prompt' => fw_workflow_render_input_prompt(
                $pdo,
                $workflow,
                $stateName,
                $state,
                $context
            ),

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
