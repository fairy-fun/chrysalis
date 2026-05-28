<?php

return [
    'workflow_id' => 'calendar_event_suggest_locations',
    'tier' => 3,
    'intent' => 'Suggest locations from prose attached to a calendar event',

    'entry_state' => 'await_calendar_event_entity_id',

    'states' => [

        'await_calendar_event_entity_id' => [
            'type' => 'input',
            'prompt' => 'What is the calendar event entity ID?',
            'expected_input' => 'calendar_event_entity_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.calendar_event_entity_id',
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

                'sql' => "
                    SELECT
                        id,
                        entity_id,
                        layer_id,
                        projection_id,
                        book_time_id,
                        event_index,
                        subevent_index,
                        sequence_index
                    FROM calendar_events
                    WHERE entity_id = :entity_id
                       OR entity_id = CONCAT('calendar_event:', :bare_entity_id)
                    LIMIT 1
                ",

                'bindings' => [
                    'entity_id' => '$input.calendar_event_entity_id',
                    'bare_entity_id' => '$input.calendar_event_entity_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'suggest_locations',
                'failure_state' => 'terminal_calendar_event_not_found',
            ],
        ],

        'suggest_locations' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'suggest_locations',

                'payload' => [
                    'calendar_event_entity_id'
                        => '$context.calendar_event.entity_id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_location_suggestions_generated',
                'failure_state' => 'terminal_location_suggestion_failed',
            ],
        ],

        'terminal_location_suggestions_generated' => [
            'type' => 'terminal',
            'message' => 'Location suggestions generated from attached prose.',

            'response' => [
                'summary' => 'Location suggestions generated from attached prose. These are advisory suggestions only and have not been applied to canonical ontology.',
                'suggestions' => '$context.location_suggestions',
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

        'terminal_location_suggestion_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to generate location suggestions from attached prose.',
        ],
    ],
];