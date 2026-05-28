<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| IMPORTANT ARCHITECTURAL DOCTRINE
|--------------------------------------------------------------------------
|
| Selector identity is NOT the same thing as workflow identity.
|
| Multiple selector forms may legitimately route to different workflows
| even when they ultimately reuse downstream rendering infrastructure.
|
| Example:
|
|     chronology selector      -> calendar_week_day_display_prose
|     reference label selector -> prose_reference_label_display
|
| This separation is intentional.
|
| The shared doctrine is:
|
|     selector
|     -> selector-specific resolution workflow
|     -> shared prose rendering pipeline
|
| DO NOT collapse workflow routing merely because downstream formatter,
| assembler, chronology derivation, or prose rendering infrastructure
| becomes shared.
|
| The architectural goal is:
|
|     shared rendering infrastructure
|     WITHOUT
|     forced workflow unification.
|
| Grammar parity between selector types should be maintained where
| appropriate, but routing distinctions may still encode meaningful
| business semantics.
|
*/

function fw_match_chat_workflow(
    string $message
): ?string {

    $message = mb_strtolower(
        trim($message)
    );

    if (
        preg_match(
            '/\\b(show|display|open)\\s+(me\\s+)?(the\\s+)?(existing|published|all)?\\s*prose\\s+for\\s+[0-9]{8}-[a-z]+\\b/i',
            $message
        ) === 1
    ) {
        return 'prose_reference_label_display';
    }

    /*
    |--------------------------------------------------------------------------
    | Prose display routing
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | These selectors intentionally route to DIFFERENT workflows.
    |
    | Dot-notation selectors:
    |
    |     1.2
    |
    | represent:
    |
    |     specific canonical Book week/day locality
    |
    | and therefore route to:
    |
    |     calendar_week_day_display_prose
    |
    | while broader week selectors:
    |
    |     week 1
    |
    | represent:
    |
    |     an entire canonical Book week
    |
    | and therefore route to:
    |
    |     calendar_week_display_prose
    |
    | DO NOT collapse these routes together merely because they share
    | downstream rendering infrastructure.
    |
    | Grammar is intentionally permissive:
    |
    |     show me prose for 1.2
    |     show me published prose for 1.2
    |     display all prose for week 1
    |     list week prose
    |
    | should all continue routing successfully.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Dot-notation week/day prose selector
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    |     show me prose for 1.2
    |     show me published prose for 1.2
    |     display all prose for 1.2
    |
    | Routes:
    |
    |     calendar_week_day_display_prose
    |
    */

    if (
        preg_match(
            '/\\b(show|display)\\s+(me\\s+)?(the\\s+)?(existing|published|all)?\\s*prose\\s+for\\s+[0-9]+\\.[0-9]+\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_week_day_display_prose';
    }

    /*
    |--------------------------------------------------------------------------
    | Explicit "week X day Y" prose selector
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    |     show me prose for week 1 day 2
    |     show me published prose for week 1, day 2
    |
    | Routes:
    |
    |     calendar_week_day_display_prose
    |
    */

    if (
        preg_match(
            '/\\b(show|display)\\s+(me\\s+)?(the\\s+)?(existing|published|all)?\\s*prose\\s+for\\s+week\\s+[0-9]+\\s*,?\\s*day\\s+[0-9]+\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_week_day_display_prose';
    }

    /*
    |--------------------------------------------------------------------------
    | Whole-week prose selector
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    |     show me prose for week 1
    |     show me published prose for week 1
    |     display all prose for week 3
    |
    | Routes:
    |
    |     calendar_week_display_prose
    |
    */

    if (
        preg_match(
            '/\\b(show|display)\\s+(me\\s+)?(the\\s+)?(existing|published|all)?\\s*prose\\s+for\\s+week(\\s+[0-9]+)?\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_week_display_prose';
    }

    /*
    |--------------------------------------------------------------------------
    | Loose prose/week browsing selector
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    |     list week prose
    |     show week prose
    |     display prose
    |
    | Routes:
    |
    |     calendar_week_display_prose
    |
    | This intentionally acts as a low-specificity fallback AFTER
    | more-specific locality selectors above.
    |
    */

    if (
        preg_match(
            '/\\b(show|display|list)\\s+(week\\s+)?prose\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_week_display_prose';
    }

    if (
        preg_match(
            '/\\b(continue|resume|use|open|attach)\\s+(calendar\\s+)?event\\s+calendar_event:\\d+\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_event_add_prose';
    }

    if (
        preg_match(
            '/\\b(add\\s+prose\\s+to|attach\\s+prose\\s+to)\\s+calendar_event:\\d+\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_event_add_prose';
    }

    if (
        preg_match(
            '/\\b(add\\s+prose\\s+to|attach\\s+prose\\s+to)\\s+(an\\s+)?existing\\s+event\\b/',
            $message
        ) === 1
    ) {
        return 'calendar_event_add_prose';
    }

    if (
        preg_match(
            '/\\b(attach\\s+prose\\s+to|add\\s+prose\\s+to)\\s+event\\s+\\d+\\b/',
            $message
        ) === 1
    ) {
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
            str_contains($message, 'approve location tags')
            || str_contains($message, 'approve location suggestions')
            || str_contains($message, 'approve locations from attached prose')
        ) {
            return 'calendar_event_approve_location_tags';
        }

        if (
            str_contains($message, 'tag locations')
            || str_contains($message, 'suggest locations')
            || str_contains($message, 'suggest place tags')
            || str_contains($message, "tag locations from an event's attached prose")
            || str_contains($message, 'tag locations from attached prose')
        ) {
            return 'calendar_event_suggest_locations';
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

        'attach prose to an existing event'
        => 'calendar_event_add_prose',

        'attach prose to an existing calendar event'
        => 'calendar_event_add_prose',

        'attach prose to event'
        => 'calendar_event_add_prose',

        'add prose to event'
        => 'calendar_event_add_prose',

        'add prose to calendar_event'
        => 'calendar_event_add_prose',

        'attach prose to calendar_event'
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
        => 'calendar_event_title_narrative_ontology',
    ];

    foreach ($intentMap as $needle => $workflowKey) {
        if (str_contains($message, $needle)) {
            return $workflowKey;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| FUTURE ARCHITECTURAL DIRECTION
|--------------------------------------------------------------------------
|
| This router currently performs:
|
|     - selector grammar recognition
|     - specificity ordering
|     - semantic workflow routing
|
| using a single ordered imperative routing surface.
|
| This remains correct for the current architecture because:
|
|     - selector precedence matters
|     - regex selectors overlap intentionally
|     - low-specificity fallbacks must remain ordered
|     - workflow semantics are not fully normalized
|
| HOWEVER:
|
| As workflow families continue expanding
| (prose, ontology, projections, tagging, chronology, etc),
| this file risks becoming a monolithic selector registry.
|
| Preferred long-term direction:
|
|     workflow_chat_intent_router.php
|         -> delegates to domain selector registries
|
| Example:
|
|     prose_selector_registry.php
|     ontology_selector_registry.php
|     projection_selector_registry.php
|     calendar_selector_registry.php
|
| allowing:
|
|     - isolated grammar ownership
|     - thinner orchestration coordinators
|     - selector-family modularity
|     - safer workflow expansion
|     - easier specificity management
|
| IMPORTANT:
|
| This should NOT become a switch() router unless the system first
| introduces canonical intent normalization and selector tokenization.
|
| Current ordered routing semantics are intentional.
|
*/
