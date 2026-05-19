<?php
declare(strict_types=1);

function fw_resolve_workflow_id_from_chat_message(
    string $userMessage
): ?string {

    $message = strtolower(trim($userMessage));

    $routes = [
        'i want to add prose to an existing calendar event' => 'calendar_event_add_prose',
        'add prose to a calendar event' => 'calendar_event_add_prose',
        'add prose to existing calendar event' => 'calendar_event_add_prose',
        'add prose to an existing calendar event' => 'calendar_event_add_prose',
        'i want to process attached prose into calendar subevents'
        => 'calendar_event_process_attached_prose',

        'process attached prose into calendar subevents'
        => 'calendar_event_process_attached_prose',

        'process attached prose'
        => 'calendar_event_process_attached_prose',
    ];

    foreach ($routes as $phrase => $workflowId) {
        if (str_contains($message, $phrase)) {
            return $workflowId;
        }
    }

    return null;
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

    return fw_resume_workflow(
        $pdo,
        $workflowId,
        $entryState,
        [
            'user_message' => $userMessage,
        ],
        $context,
        $snapshots
    );
}