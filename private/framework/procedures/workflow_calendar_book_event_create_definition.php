<?php

return [
    'workflow_id' => 'calendar_book_event_create',
    'tier' => 1,
    'intent' => 'Create a calendar event using canonical Book locality identity.',

    'entry_state' => 'await_projection_id',

    'states' => [

        'await_projection_id' => [
            'type' => 'input',
            'prompt' => 'Which book should this event belong to? You can answer with a book label such as Book 1.',
            'expected_input' => 'projection_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.projection_id',
                'cases' => [
                    '' => 'terminal_missing_projection_id',
                ],
                'default' => 'await_week_index',
            ],
        ],

        'await_week_index' => [
            'type' => 'input',
            'prompt' => 'Which week should this event belong to? You can answer with a number or a label such as Week 1.',
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
            'prompt' => 'Which day should this event belong to? You can answer with a day name, a number, or a label such as Day 2.',
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
            'prompt' => 'Which time slot should this event belong to? You can answer with a number or a label such as Time 1.',
            'expected_input' => 'time_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.time_index',
                'cases' => [
                    '' => 'terminal_missing_time_index',
                ],
                'default' => 'normalize_book_event_input',
            ],
        ],

        'normalize_book_event_input' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'normalize_book_event_input',

                'payload' => [
                    'projection' => '$input.projection_id',
                    'week' => '$input.week_index',
                    'day' => '$input.day_index',
                    'time' => '$input.time_index',
                ],
            ],

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
                'next' => 'validate_book_week',
                'failure_state' => 'terminal_unsupported_projection_type',
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
                        entity_id,
                        projection_id,
                        week_index
                    FROM calendar_book_weeks
                    WHERE projection_id = :projection_id
                      AND week_index = :week_index
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.calendar_normalized_input.projection_id',
                    'week_index' => '$context.calendar_normalized_input.week_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'validate_book_day',
                'failure_state' => 'terminal_book_week_not_found',
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
                        id,
                        entity_id,
                        projection_id,
                        week_id,
                        day_index,
                        day_of_week_id
                    FROM calendar_book_days
                    WHERE projection_id = :projection_id
                      AND week_id = :week_id
                      AND day_index = :day_index
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.calendar_normalized_input.projection_id',
                    'week_id' => '$context.book_week.id',
                    'day_index' => '$context.calendar_normalized_input.day_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'validate_book_time',
                'failure_state' => 'terminal_book_day_not_found',
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
                        t.entity_id,
                        t.projection_id,
                        t.day_id,
                        t.time_index,
                        t.summary,
                        t.notes,
                        t.time_label_id,
                        cv.label AS time_label,
                        COALESCE(
                            NULLIF(TRIM(cv.label), \'\'),
                            NULLIF(TRIM(t.summary), \'\'),
                            NULLIF(TRIM(t.notes), \'\'),
                            CONCAT(\'Time \', t.time_index)
                        ) AS display_label
                    FROM calendar_book_times t
                    LEFT JOIN calendar_time_label_classvals cv
                        ON cv.id = t.time_label_id
                    WHERE t.projection_id = :projection_id
                      AND t.day_id = :day_id
                      AND t.time_index = :time_index
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.calendar_normalized_input.projection_id',
                    'day_id' => '$context.book_day.id',
                    'time_index' => '$context.calendar_normalized_input.time_index',
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
                    'projection_id' => '$context.calendar_normalized_input.projection_id',
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
            'handoff_packet' => '$context.handoff_packet',
        ],

        'terminal_event_creation_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to create calendar event.',
        ],

        'terminal_missing_projection_id' => [
            'type' => 'terminal',
            'message' => 'A book projection is required.',
        ],

        'terminal_projection_not_found' => [
            'type' => 'terminal',
            'message' => 'Book projection not found.',
        ],

        'terminal_unsupported_projection_type' => [
            'type' => 'terminal',
            'message' => 'This workflow currently supports Book projections only.',
        ],

        'terminal_missing_week_index' => [
            'type' => 'terminal',
            'message' => 'A week is required.',
        ],

        'terminal_book_week_not_found' => [
            'type' => 'terminal',
            'message' => 'Book week does not exist in this projection.',
        ],

        'terminal_missing_day_index' => [
            'type' => 'terminal',
            'message' => 'A day is required.',
        ],

        'terminal_book_day_not_found' => [
            'type' => 'terminal',
            'message' => 'Book day does not exist in this week.',
        ],

        'terminal_missing_time_index' => [
            'type' => 'terminal',
            'message' => 'A time slot is required.',
        ],

        'terminal_book_time_not_found' => [
            'type' => 'terminal',
            'message' => 'Book time does not exist for this day.',
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
