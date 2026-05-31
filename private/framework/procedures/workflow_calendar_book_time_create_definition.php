<?php

return [

    'workflow_id' => 'calendar_book_time_create',

    'tier' => 0,

    'intent'
        => 'Create a canonical Book chronology time container.',

    'entry_state' => 'await_projection_id',

    'states' => [

        'await_projection_id' => [

            'type' => 'input',

            'prompt'
                => 'Which book should this time slot belong to? You can answer with a book label such as Book 1.',

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

            'prompt'
                => 'Which week should this time slot belong to? You can answer with a number or a label such as Week 1.',

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

            'prompt'
                => 'Which day should this time slot belong to? You can answer with a day name, a number, or a label such as Day 2.',

            'expected_input' => 'day_index',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.day_index',

                'cases' => [
                    '' => 'terminal_missing_day_index',
                ],

                'default' => 'normalize_book_time_input',
            ],
        ],

        'normalize_book_time_input' => [

            'type' => 'action',

            'action' => [

                'driver' => 'calendar',
                'operation' => 'normalize_book_time_input',

                'payload' => [
                    'projection' => '$input.projection_id',
                    'week' => '$input.week_index',
                    'day' => '$input.day_index',
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
                'next' => 'resolve_next_time_index',
                'failure_state' => 'terminal_book_day_not_found',
            ],
        ],

        'resolve_next_time_index' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'next_book_time',

                'sql' => '
                    SELECT
                        COALESCE(MAX(time_index), 0) + 1 AS next_time_index
                    FROM calendar_book_times
                    WHERE projection_id = :projection_id
                      AND day_id = :day_id
                ',

                'bindings' => [
                    'projection_id' => '$context.calendar_normalized_input.projection_id',
                    'day_id' => '$context.book_day.id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_time_label',
                'failure_state' => 'terminal_next_time_index_resolution_failed',
            ],
        ],

        'await_time_label' => [

            'type' => 'input',

            'prompt'
                => 'What should this time slot be called? Examples: Morning, Afternoon, Evening, Time 4, Late Night.',

            'expected_input' => 'time_label',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.time_label',

                'cases' => [
                    '' => 'terminal_missing_time_label',
                ],

                'default' => 'create_book_time',
            ],
        ],

        'create_book_time' => [

            'type' => 'action',

            'action' => [

                'driver' => 'calendar',
                'operation' => 'create_book_time',

                'payload' => [
                    'projection_id' => '$context.calendar_normalized_input.projection_id',
                    'week_id' => '$context.book_week.id',
                    'day_id' => '$context.book_day.id',
                    'time_index' => '$context.next_book_time.next_time_index',
                    'summary' => '$input.time_label',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_book_time_created',
                'failure_state' => 'terminal_book_time_creation_failed',
            ],
        ],

        'terminal_book_time_created' => [

            'type' => 'terminal',

            'message'
                => 'Book chronology time created successfully.',

            'response' => [
                'calendar_time_id' => '$context.calendar_book_time.id',
                'calendar_book_time_id' => '$context.calendar_book_time.id',
                'entity_id' => '$context.calendar_book_time.entity_id',
                'summary' => '$context.calendar_book_time.summary',
                'sequence_index' => '$context.calendar_book_time.sequence_index',
                'time_index' => '$context.calendar_book_time.time_index',
                'book_projection_code' => '$context.calendar_normalized_input.book_projection_code',
                'week_index' => '$context.calendar_normalized_input.week_index',
                'day_index' => '$context.calendar_normalized_input.day_index',
            ],
        ],

        'terminal_book_time_creation_failed' => [

            'type' => 'terminal',

            'message'
                => 'Failed to create Book chronology time.',
        ],

        'terminal_missing_projection_id' => [

            'type' => 'terminal',

            'message'
                => 'A book projection is required.',
        ],

        'terminal_projection_not_found' => [

            'type' => 'terminal',

            'message'
                => 'Book projection not found.',
        ],

        'terminal_unsupported_projection_type' => [

            'type' => 'terminal',

            'message'
                => 'This workflow currently supports Book projections only.',
        ],

        'terminal_missing_week_index' => [

            'type' => 'terminal',

            'message'
                => 'A week is required.',
        ],

        'terminal_book_week_not_found' => [

            'type' => 'terminal',

            'message'
                => 'Book week does not exist in this projection.',
        ],

        'terminal_missing_day_index' => [

            'type' => 'terminal',

            'message'
                => 'A day is required.',
        ],

        'terminal_book_day_not_found' => [

            'type' => 'terminal',

            'message'
                => 'Book day does not exist in this week.',
        ],

        'terminal_next_time_index_resolution_failed' => [

            'type' => 'terminal',

            'message'
                => 'Failed to determine the next canonical time slot for this day.',
        ],

        'terminal_missing_time_label' => [

            'type' => 'terminal',

            'message'
                => 'A time slot label is required.',
        ],
    ],

    'initial_context' => [

        'required_projection_type_id'
            => 'projection_type_book',
    ],
];