<?php

return [
    'workflow_id' => 'calendar_event_create',
    'tier' => 1,
    'intent' => 'Create a calendar event through ontology-guided locality interview',

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
                'next' => 'await_book_week_index',
                'failure_state' => 'terminal_unsupported_projection_type',
            ],
        ],

        'await_book_week_index' => [
            'type' => 'input',
            'prompt' => 'What week index should this Book event belong to?',
            'expected_input' => 'week_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.week_index',
                'cases' => [
                    '' => 'terminal_missing_week_index',
                ],
                'default' => 'validate_book_week',
            ],
        ],

        'validate_book_week' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'book_week',

                'sql' => '
                    SELECT
                        id,
                        projection_id,
                        week_index
                    FROM calendar_book_weeks
                    WHERE projection_id = :projection_id
                      AND week_index = :week_index
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.projection.id',
                    'week_index' => '$input.week_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_book_day_index',
                'failure_state' => 'terminal_book_week_not_found',
            ],
        ],

        'await_book_day_index' => [
            'type' => 'input',
            'prompt' => 'What day index within that week?',
            'expected_input' => 'day_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.day_index',
                'cases' => [
                    '' => 'terminal_missing_day_index',
                ],
                'default' => 'validate_book_day',
            ],
        ],

        'validate_book_day' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'book_day',

                'sql' => '
                    SELECT
                        d.id,
                        d.book_week_id,
                        d.day_index
                    FROM calendar_book_days d
                    INNER JOIN calendar_book_weeks w
                        ON w.id = d.book_week_id
                    WHERE w.projection_id = :projection_id
                      AND w.week_index = :week_index
                      AND d.day_index = :day_index
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.projection.id',
                    'week_index' => '$input.week_index',
                    'day_index' => '$input.day_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_book_time_index',
                'failure_state' => 'terminal_book_day_not_found',
            ],
        ],

        'await_book_time_index' => [
            'type' => 'input',
            'prompt' => 'What time index within that day?',
            'expected_input' => 'time_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.time_index',
                'cases' => [
                    '' => 'terminal_missing_time_index',
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
                        t.id,
                        t.book_day_id,
                        t.time_index
                    FROM calendar_book_times t
                    INNER JOIN calendar_book_days d
                        ON d.id = t.book_day_id
                    INNER JOIN calendar_book_weeks w
                        ON w.id = d.book_week_id
                    WHERE w.projection_id = :projection_id
                      AND w.week_index = :week_index
                      AND d.day_index = :day_index
                      AND t.time_index = :time_index
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.projection.id',
                    'week_index' => '$input.week_index',
                    'day_index' => '$input.day_index',
                    'time_index' => '$input.time_index',
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

        'terminal_missing_week_index' => [
            'type' => 'terminal',
            'message' => 'A week_index is required.',
        ],

        'terminal_book_week_not_found' => [
            'type' => 'terminal',
            'message' => 'Week does not exist in this Book projection.',
        ],

        'terminal_missing_day_index' => [
            'type' => 'terminal',
            'message' => 'A day_index is required.',
        ],

        'terminal_book_day_not_found' => [
            'type' => 'terminal',
            'message' => 'Day does not exist in this Book chronology.',
        ],

        'terminal_missing_time_index' => [
            'type' => 'terminal',
            'message' => 'A time_index is required.',
        ],

        'terminal_book_time_not_found' => [
            'type' => 'terminal',
            'message' => 'Time does not exist in this Book chronology.',
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
