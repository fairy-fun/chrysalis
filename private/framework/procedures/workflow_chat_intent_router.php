<?php
declare(strict_types=1);

function fw_match_chat_workflow(
    string $message
): ?string {

    $message = mb_strtolower(
        trim($message)
    );

    $map = [

        'i want to add prose to an existing calendar event'
        => 'calendar_event_add_prose',

        'add prose to an existing calendar event'
        => 'calendar_event_add_prose',

        'add prose to a calendar event'
        => 'calendar_event_add_prose',

        'calendar event prose'
        => 'calendar_event_add_prose',

        'process calendar prose'
        => 'calendar_event_process_attached_prose',

        'i want to process attached prose into calendar subevents'
        => 'calendar_event_process_attached_prose',

        'process attached prose into calendar subevents'
        => 'calendar_event_process_attached_prose',

        'process attached prose'
        => 'calendar_event_process_attached_prose',

        'process prose attached to'
        => 'calendar_event_process_attached_prose',

        'process attached prose for'
        => 'calendar_event_process_attached_prose',

        'process prose for'
        => 'calendar_event_process_attached_prose',
    ];

    foreach ($map as $phrase => $workflowId) {

        if (str_contains($message, $phrase)) {
            return $workflowId;
        }
    }

    return null;
}