<?php

return [
    'workflow_id' => 'calendar_event_process_attached_prose',
    'tier' => 3,
    'intent' => 'Process attached prose into calendar subevents',

    'entry_state' => 'await_calendar_event_entity_id',

    /*
    |--------------------------------------------------------------------------
    | 1. Resolve calendar event
    |--------------------------------------------------------------------------
    */

    'states' => [

        'await_calendar_event_entity_id' => [
            'type' => 'input',
            'prompt' => 'What is the calendar event entity ID?',
            'expected_input' => 'entity_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.entity_id',
                'cases' => [
                    '' => 'terminal_missing_entity_id',
                ],
                'default' => 'validate_calendar_event_entity',
            ],
        ],

        'validate_calendar_event_entity' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'calendar_event',

                'sql' => '
                    SELECT
                        id,
                        entity_id,
                        parent_event_id,
                        layer_id,
                        projection_id
                    FROM calendar_events
                    WHERE entity_id = :entity_id
                    LIMIT 1
                ',

                'bindings' => [
                    'entity_id' => '$input.entity_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'inspect_existing_subevents',
                'failure_state' => 'terminal_calendar_event_not_found',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 2. IDEMPOTENCY GUARD (CRITICAL)
        |--------------------------------------------------------------------------
        */

        'inspect_existing_subevents' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'existing_subevent',

                'sql' => '
                    SELECT
                        id,
                        entity_id
                    FROM calendar_events
                    WHERE parent_event_id = :event_id
                    LIMIT 1
                ',

                'bindings' => [
                    'event_id' => '$context.calendar_event.id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_subevents_already_exist',
                'failure_state' => 'process_attached_prose',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 3. PROSE PROCESSING (TIER 3 ORCHESTRATION)
        |--------------------------------------------------------------------------
        */

        'process_attached_prose' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'process_attached_prose',

                'payload' => [
                    'calendar_event_id' => '$context.calendar_event.entity_id',
                    'calendar_event_db_id' => '$context.calendar_event.id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_subevents_created',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | TERMINALS
        |--------------------------------------------------------------------------
        */

        'terminal_subevents_already_exist' => [
            'type' => 'terminal',

            'message' =>
                'Subevents already exist for this calendar event.',

            'response' => [
                'idempotent' => true,
                'artifact' => [
                    'parent_event_entity_id' => '$context.calendar_event.entity_id',
                    'subevents' => [],
                ],
            ],
        ],

        'terminal_subevents_created' => [
            'type' => 'terminal',

            'message' =>
                'Calendar prose successfully processed into subevents.',

            'response' => [
                'artifact' => '$context.artifact',
                'workflow' => 'calendar_event_process_attached_prose',
                'tier' => 3,
            ],
        ],

        'terminal_missing_entity_id' => [
            'type' => 'terminal',
            'message' => 'A calendar_event entity_id is required.',
        ],

        'terminal_calendar_event_not_found' => [
            'type' => 'terminal',
            'message' => 'No calendar_event found for that entity_id.',
        ],
    ],
];