<?php

return [
    'workflow_id' => 'calendar_book_event_create',
    'tier' => 1,
    'intent' => 'Create a Book calendar event in the correct projection',

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
                'next' => 'assert_book_projection',
                'failure_state' => 'terminal_projection_not_found',
            ],
        ],

        'assert_book_projection' => [
            'type' => 'action',

            'assert' => [
                'left' => '$context.projection.projection_type_id',
                'operator' => 'equals',
                'right' => 'projection_type_book',
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_week_index',
                'failure_state' => 'terminal_unsupported_projection_type',
            ],
        ],

        'await_week_index' => [
            'type' => 'input',
            'prompt' => 'What is the week index?',
            'expected_input' => 'week_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.week_index',
                'cases' => [
                    '' => 'terminal_missing_week_index',
                ],
                'default' => 'await_day_index',
            ],
        ],

        'await_day_index' => [
            'type' => 'input',
            'prompt' => 'What is the day index?',
            'expected_input' => 'day_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.day_index',
                'cases' => [
                    '' => 'terminal_missing_day_index',
                ],
                'default' => 'await_time_index',
            ],
        ],

        'await_time_index' => [
            'type' => 'input',
            'prompt' => 'What is the time index?',
            'expected_input' => 'time_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.time_index',
                'cases' => [
                    '' => 'terminal_missing_time_index',
                ],
                'default' => 'await_event_index',
            ],
        ],

        'await_event_index' => [
            'type' => 'input',
            'prompt' => 'What is the event index?',
            'expected_input' => 'event_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.event_index',
                'cases' => [
                    '' => 'terminal_missing_event_index',
                ],
                'default' => 'await_summary',
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
                    'week_index' => '$input.week_index',
                    'day_index' => '$input.day_index',
                    'time_index' => '$input.time_index',
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
            'message' => 'Book calendar event created successfully.',
        ],

        'terminal_event_creation_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to create Book calendar event.',
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
            'message' => 'Tier 1 Book event creation supports Book projections only.',
        ],

        'terminal_missing_week_index' => [
            'type' => 'terminal',
            'message' => 'A week_index is required.',
        ],

        'terminal_missing_day_index' => [
            'type' => 'terminal',
            'message' => 'A day_index is required.',
        ],

        'terminal_missing_time_index' => [
            'type' => 'terminal',
            'message' => 'A time_index is required.',
        ],

        'terminal_missing_event_index' => [
            'type' => 'terminal',
            'message' => 'An event_index is required.',
        ],

        'terminal_missing_summary' => [
            'type' => 'terminal',
            'message' => 'A summary is required.',
        ],
    ],
];