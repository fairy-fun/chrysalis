<?php
declare(strict_types=1);

function fw_match_chat_workflow(
    string $message
): ?string {

    $message = mb_strtolower(
        trim($message)
    );

    if (
        preg_match(
            '/\\b(show|display)\\s+(me\\s+)?(the\\s+)?(existing|published|all)\\s+prose\\s+for\\s+[0-9]+\\.[0-9]+\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_week_day_display_prose';
    }

    if (
        preg_match(
            '/\\b(show|display)\\s+(me\\s+)?(the\\s+)?(existing|published|all)\\s+prose\\s+for\\s+week\\s+[0-9]+\\s*,?\\s*day\\s+[0-9]+\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_week_day_display_prose';
    }

    if (
        preg_match(
            '/\\b(show|display)\\s+(me\\s+)?(the\\s+)?(existing|published|all)\\s+prose\\s+for\\s+week(\\s+[0-9]+)?\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_week_display_prose';
    }

    if (
        preg_match(
            '/\\b(show|display|list)\\s+(week\\s+)?prose\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_week_display_prose';
    }

    if (preg_match('/\\b(continue|resume|use|open)\\s+(calendar\\s+)?event\\s+calendar_event:\\d+\\b/', $message) === 1) {
        return 'calendar_event_add_prose';
    }

    if (preg_match('/\\bcalendar_event:\\d+\\b/', $message) === 1
        && str_contains($message, 'beat')) {
        return 'calendar_event_add_prose';
    }

    $intentMap = [

        'create event'
        => 'calendar_event_create',

        'create an event'
        => 'calendar_event_create',

        'create calendar event'
        => 'calendar_event_create',

        'create a calendar event'
        => 'calendar_event_create',

        'add event'
        => 'calendar_event_create',

        'add an event'
        => 'calendar_event_create',

        'add calendar event'
        => 'calendar_event_create',

        'add a calendar event'
        => 'calendar_event_create',

        'create book event'
        => 'calendar_book_event_create',

        'create a book event'
        => 'calendar_book_event_create',

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

        'show prose for'
        => 'calendar_day_display_prose',

        'display prose for'
        => 'calendar_day_display_prose',

        'show all prose for'
        => 'calendar_day_display_prose',

        'display all prose for'
        => 'calendar_day_display_prose',

        'show prose on'
        => 'calendar_day_display_prose',
    ];

    foreach ($intentMap as $phrase => $workflowId) {

        if (str_contains($message, $phrase)) {
            return $workflowId;
        }
    }

    return null;
}
