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
            '/\bweek\s+([0-9]+)\b/i',
            $userMessage,
            $matches
        ) === 1
    ) {
        $input['week'] = $matches[1];
    }

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

    return fw_resume_workflow(
        $pdo,
        $workflowId,
        $entryState,
        fw_extract_chat_bootstrap_input(
            $userMessage
        ),
        array_merge(
            $initialContext,
            $context
        ),
        $snapshots
    );
}
