<?php
declare(strict_types=1);

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
            '/\\b(show|display)\\s+(me\\s+)?(the\\s+)?(published|all)\\s+prose\\s+for\\b/',
            $normalisedMessage
        ) === 1
    ) {
        $input['prose_mode'] = 'published';
    } else {
        $input['prose_mode'] = 'export';
    }

    if (
        preg_match(
            '/\\b(calendar_event:\\d+)\\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['calendar_event_entity_id'] = $matches[1];
    }

    if (
        preg_match(
            '/\\b(\\d{8}-[a-z]+)\\b/i',
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
            '/\\b([0-9]+)\\.([0-9]+)\\b/',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['week'] = $matches[1];
        $input['day'] = $matches[2];

        return $input;
    }

    if (
        preg_match(
            '/\\bweek\\s+([0-9]+)\\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['week'] = $matches[1];
    }

    if (
        preg_match(
            '/\\bday\\s+([0-9]+)\\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['day'] = $matches[1];
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
