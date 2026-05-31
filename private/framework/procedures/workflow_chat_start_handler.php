<?php
declare(strict_types=1);

function fw_extract_chat_bootstrap_book_number(
    string $userMessage
): ?int {

    if (
        preg_match(
            '/\bbook\s+(\d+)\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        return (int)$matches[1];
    }

    if (
        preg_match(
            '/\bBOOK-(\d{1,3})\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        return (int)$matches[1];
    }

    if (
        preg_match(
            '/\bbook_projection_BOOK-(\d{3})\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        return (int)$matches[1];
    }

    return null;
}

function fw_extract_chat_bootstrap_projection_code(
    string $userMessage
): ?string {

    $bookNumber = fw_extract_chat_bootstrap_book_number(
        $userMessage
    );

    if (!is_int($bookNumber) || $bookNumber < 1) {
        return null;
    }

    return 'book_projection_BOOK-' . str_pad(
        (string)$bookNumber,
        3,
        '0',
        STR_PAD_LEFT
    );
}

function fw_extract_chat_bootstrap_day_index(
    string $userMessage
): ?int {

    if (
        preg_match(
            '/\bday\s+(\d+)\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        return (int)$matches[1];
    }

    $dayMap = [
        'sunday' => 1,
        'monday' => 2,
        'tuesday' => 3,
        'wednesday' => 4,
        'thursday' => 5,
        'friday' => 6,
        'saturday' => 7,
    ];

    foreach ($dayMap as $label => $dayIndex) {
        if (
            preg_match(
                '/\b' . preg_quote($label, '/') . '\b/i',
                $userMessage
            ) === 1
        ) {
            return $dayIndex;
        }
    }

    return null;
}

function fw_resolve_workflow_id_from_chat_message(
    string $userMessage
): ?string {

    return fw_match_chat_workflow(
        $userMessage
    );
}

function fw_extract_chat_bootstrap_input(
    string $userMessage
): array {

    $input = [
        'user_message' => $userMessage,
    ];

    $normalisedMessage = mb_strtolower(
        trim($userMessage)
    );

    if (
        preg_match(
            '/\b(show|display)\s+(me\s+)?(the\s+)?(published|all)\s+prose\s+for\b/',
            $normalisedMessage
        ) === 1
    ) {
        $input['prose_mode'] = 'published';
    } else {
        $input['prose_mode'] = 'export';
    }

    $projectionCode = fw_extract_chat_bootstrap_projection_code(
        $userMessage
    );

    if (is_string($projectionCode) && $projectionCode !== '') {
        $input['projection_id'] = $projectionCode;
    }

    if (
        preg_match(
            '/\b(calendar_event:\d+)\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['calendar_event_entity_id'] = $matches[1];
    }

    if (
        preg_match(
            '/\b(\d{8}-[a-z]+)\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['reference_label'] = strtolower(
            trim($matches[1])
        );

        return $input;
    }

    if (
        preg_match(
            '/\b([0-9]+)\.([0-9]+)\b/',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['week'] = $matches[1];
        $input['day'] = $matches[2];
        $input['week_index'] = $matches[1];
        $input['day_index'] = $matches[2];

        return $input;
    }

    if (
        preg_match(
            '/\bweek\s+([0-9]+)\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['week'] = $matches[1];
        $input['week_index'] = $matches[1];
    }

    $dayIndex = fw_extract_chat_bootstrap_day_index(
        $userMessage
    );

    if (is_int($dayIndex) && $dayIndex > 0) {
        $input['day'] = (string)$dayIndex;
        $input['day_index'] = (string)$dayIndex;
    }

    return $input;
}

function fw_resolve_reference_label_selector(
    PDO $pdo,
    array $input
): array {

    if (isset($input['calendar_event_entity_id'])) {
        return $input;
    }

    $referenceLabel = $input['reference_label'] ?? null;

    if (!is_string($referenceLabel) || trim($referenceLabel) === '') {
        return $input;
    }

    $stmt = $pdo->prepare('
        SELECT
            ce.entity_id AS calendar_event_entity_id
        FROM calendar_event_reference_labels cerl
        INNER JOIN calendar_events ce
            ON ce.id = cerl.calendar_event_id
        WHERE cerl.reference_label = :reference_label
        LIMIT 1
    ');

    $stmt->execute([
        ':reference_label' => strtolower(
            trim($referenceLabel)
        ),
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        return $input;
    }

    $input['calendar_event_entity_id'] = $row['calendar_event_entity_id'];

    return $input;
}

function fw_start_workflow_from_chat(
    PDO $pdo,
    string $userMessage,
    array $context = [],
    array $snapshots = []
): array {

    $workflowId = fw_resolve_workflow_id_from_chat_message($userMessage);

    if ($workflowId === null) {
        return [
            'status' => 'unrouted',
            'message' => 'No workflow route matched the user message.',
            'user_message' => $userMessage,
            'context' => $context,
            'snapshots' => $snapshots,
        ];
    }

    $definition = fw_get_workflow_definition($workflowId);

    $entryState = $definition['entry_state']
        ?? $definition['initial_state']
        ?? null;

    if (!is_string($entryState) || $entryState === '') {
        throw new RuntimeException(
            'Workflow definition missing entry_state or initial_state.'
        );
    }

    $initialContext = $definition['initial_context'] ?? [];

    if (!is_array($initialContext)) {
        $initialContext = [];
    }

    $input = fw_extract_chat_bootstrap_input(
        $userMessage
    );

    $input = fw_resolve_reference_label_selector(
        $pdo,
        $input
    );

    return fw_resume_workflow(
        $pdo,
        $workflowId,
        $entryState,
        $input,
        array_merge(
            $initialContext,
            $context
        ),
        $snapshots
    );
}
