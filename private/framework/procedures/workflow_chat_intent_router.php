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

    if (preg_match('/\bcalendar_event:\d+\b/', $message) === 1
        && str_contains($message, 'subevent')) {
        return 'calendar_event_process_attached_prose';
    }

    if (
        str_contains($message, 'add reference label')
        || str_contains($message, 'add a reference label')
        || str_contains($message, 'attach reference label')
        || str_contains($message, 'assign reference label')
    ) {
        return 'calendar_event_add_reference_label';
    }

    if (
        str_contains($message, 'set published prose draft')
        || str_contains($message, 'set canonical export draft')
        || str_contains($message, 'elevate prose draft')
        || str_contains($message, 'change canonical export target')
    ) {
        return 'prose_projection_set_published_draft';
    }

    if (
        str_contains($message, 'add a new prose draft to a prose family')
        || str_contains($message, 'add new prose draft to a prose family')
        || str_contains($message, 'add a prose draft to a prose family')
        || str_contains($message, 'new prose draft to a prose family')
    ) {
        return 'prose_family_add_draft';
    }

    if (
        str_contains($message, 'approve character tags')
        || str_contains($message, 'approve character suggestions')
    ) {
        return 'calendar_event_approve_character_tags';
    }

    if (
        str_contains($message, 'tag characters')
        || str_contains($message, 'suggest characters')
    ) {
        return 'calendar_event_suggest_characters';
    }

    if (
        str_contains($message, 'beat and title')
        || str_contains($message, 'derive beat')
    ) {
        return 'calendar_event_title_narrative_ontology';
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

        'i want to set the canonical export draft'
        => 'prose_projection_set_published_draft',

        'set the canonical export draft'
        => 'prose_projection_set_published_draft',

        'i want to elevate a prose draft'
        => 'prose_projection_set_published_draft',

        'elevate a prose draft'
        => 'prose_projection_set_published_draft',

        'i want to add a new prose draft to a prose family'
        => 'prose_family_add_draft',

        'add a new prose draft to a prose family'
        => 'prose_family_add_draft',

        'add new prose draft to a prose family'
        => 'prose_family_add_draft',

        'add a prose draft to a prose family'
        => 'prose_family_add_draft',

        'i want to add prose to an existing calendar event'
        => 'calendar_event_add_prose',

        'add prose to an existing calendar event'
        => 'calendar_event_add_prose',

        'add prose to a calendar event'
        => 'calendar_event_add_prose',

        'calendar event prose'
        => 'calendar_event_add_prose',

        'i want to add a reference label to an existing prose attachment'
        => 'calendar_event_add_reference_label',

        'add a reference label to an existing prose attachment'
        => 'calendar_event_add_reference_label',

        'add reference label to prose attachment'
        => 'calendar_event_add_reference_label',

        'process calendar prose'
        => 'calendar_event_process_attached_prose',

        'i want to process attached prose into its beat and title'
            => 'calendar_event_title_narrative_ontology',

        'process attached prose into its beat and title'