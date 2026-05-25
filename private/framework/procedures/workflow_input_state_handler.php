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
        ($workflow['workflow_id'] ?? null) === 'prose_projection_set_published_draft'
        && $stateName === 'await_draft_id'
    ) {
        $familyId = (int)(
            $context['prose_family']['id']
            ?? 0
        );

        if ($familyId < 1) {
            return $prompt;
        }

        $stmt = $pdo->prepare('
            SELECT
                id,
                entity_id,
                title,
                summary,
                draft_status_id,
                created_at,
                updated_at
            FROM prose_drafts
            WHERE prose_family_id = :prose_family_id
            ORDER BY
                created_at DESC,
                id DESC
        ');

        $stmt->execute([
            ':prose_family_id' => $familyId,
        ]);

        $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($drafts === []) {
            return $prompt
                . "\n\nNo drafts were found in this prose family.";
        }

        $publishedDraftId = $context['prose_family']['published_prose_draft_id'] ?? null;
        $lines = [];

        foreach ($drafts as $draft) {
            $id = (int)($draft['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $title = trim((string)($draft['title'] ?? ''));

            if ($title === '') {
                $title = 'Untitled prose draft';
            }

            $summary = trim((string)($draft['summary'] ?? ''));
            $createdAt = trim((string)($draft['created_at'] ?? ''));
            $status = trim((string)($draft['draft_status_id'] ?? ''));
            $marker = ((string)$publishedDraftId !== '' && (int)$publishedDraftId === $id)
                ? ' [current export]'
                : '';

            $line = sprintf(
                'ID %d%s — %s',
                $id,
                $marker,
                $title
            );

            if ($createdAt !== '') {
                $line .= ' — created ' . $createdAt;
            }

            if ($status !== '') {
                $line .= ' — status ' . $status;
            }

            if ($summary !== '') {
                $line .= ' — ' . $summary;
            }

            $lines[] = $line;
        }

        if ($lines === []) {
            return $prompt;
        }

        return $prompt
            . "\n\nDrafts in this prose family, newest first:\n"
            . implode("\n", $lines)
            . "\n\nReply with the exact prose_drafts.id. Do not reply with 'most recent'; publication must be an explicit author selection.";
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
