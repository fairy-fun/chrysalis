<?php

return [
    'workflow_id' => 'calendar_event_create',
    'tier' => 1,
    'intent' => 'Create a calendar event using canonical Book locality identity.',

    'entry_state' => 'await_projection_id',

    'states' => [

        'await_projection_id' => [
            'type' => 'input',
            'prompt' => 'Which projection should the event belong to?',
            'expected_input' => 'projection_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.projection_id',
                'cases' => [
                    '' => 'terminal_missing_projection_id',
                ],
                'default' => 'validate_projection',
            ],
        ],

        'validate_projection' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'projection',

                'sql' => '
                    SELECT
                        id,
                        projection_type_id,
                        projection_code
                    FROM calendar_projections
                    WHERE id = :projection_id
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$input.projection_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'route_projection_ontology',
                'failure_state' => 'terminal_projection_not_found',
            ],
        ],

        'route_projection_ontology' => [
            'type' => 'action',

            'assert' => [
                'left' => '$context.projection.projection_type_id',
                'operator' => 'equals',
                'right' => '$context.required_projection_type_id',
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_book_time_id',
                'failure_state' => 'terminal_unsupported_projection_type',
            ],
        ],

        'await_book_time_id' => [
            'type' => 'input',
            'prompt' => 'Which canonical Book time should this event belong to?',
            'expected_input' => 'book_time_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.book_time_id',
                'cases' => [
                    '' => 'terminal_missing_book_time_id',
                ],
                'default' => 'validate_book_time',
            ],
        ],

        'validate_book_time' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'book_time',

                'sql' => '
                    SELECT
                        id,
                        projection_id,
                        day_id,
                        time_index
                    FROM calendar_book_times
                    WHERE id = :book_time_id
                      AND projection_id = :projection_id
                    LIMIT 1
                ',

                'bindings' => [
                    'book_time_id' => '$input.book_time_id',
                    'projection_id' => '$context.projection.id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_summary',
                'failure_state' => 'terminal_book_time_not_found',
            ],
        ],

        'await_summary' => [
            'type' => 'input',
            'prompt' => 'Enter a summary for the event.',
            'expected_input' => 'summary',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.summary',
                'cases' => [
                    '' => 'terminal_missing_summary',
                ],
                'default' => 'await_optional_event_index',
            ],
        ],

        'await_optional_event_index' => [
            'type' => 'input',
            'prompt' => 'What event index should this use? Leave blank to use the next available event index.',
            'expected_input' => 'event_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.event_index',
                'cases' => [
                    '' => 'create_book_calendar_event',
                ],
                'default' => 'create_book_calendar_event',
            ],
        ],

        'create_book_calendar_event' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'create_book_event',

                'payload' => [
                    'projection_id' => '$context.projection.id',
                    'book_time_id' => '$context.book_time.id',
                    'event_index' => '$input.event_index',
                    'summary' => '$input.summary',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_event_created',
                'failure_state' => 'terminal_event_creation_failed',
            ],
        ],

        'terminal_event_created' => [
            'type' => 'terminal',
            'message' => 'Calendar event created successfully.',
        ],

        'terminal_event_creation_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to create calendar event.',
        ],

        'terminal_missing_projection_id' => [
            'type' => 'terminal',
            'message' => 'A projection_id is required.',
        ],

        'terminal_projection_not_found' => [
            'type' => 'terminal',
            'message' => 'Projection not found.',
        ],

        'terminal_unsupported_projection_type' => [
            'type' => 'terminal',
            'message' => 'This workflow currently supports Book projections only.',
        ],

        'terminal_missing_book_time_id' => [
            'type' => 'terminal',
            'message' => 'A book_time_id is required.',
        ],

        'terminal_book_time_not_found' => [
            'type' => 'terminal',
            'message' => 'Book time does not exist in this projection.',
        ],

        'terminal_missing_summary' => [
            'type' => 'terminal',
            'message' => 'A summary is required.',
        ],
    ],

    'initial_context' => [
        'required_projection_type_id' => 'projection_type_book',
    ],
];
