<?php

return [

    'workflow_id' => 'calendar_book_time_create',

    'tier' => 0,

    'intent'
        => 'Create a canonical Book chronology time container',

    'entry_state' => 'await_projection_id',

    'states' => [

        'await_projection_id' => [

            'type' => 'input',

            'prompt'
                => 'Which projection should this Book time belong to?',

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

                'failure_state'
                    => 'terminal_projection_not_found',
            ],
        ],

        'assert_book_projection' => [

            'type' => 'action',

            'assert' => [

                'left'
                    => '$context.projection.projection_type_id',

                'operator' => 'equals',

                'right'
                    => 'projection_type_book',
            ],

            'transition' => [

                'driver' => 'boolean',

                'next' => 'await_week_index',

                'failure_state'
                    => 'terminal_unsupported_projection_type',
            ],
        ],

        'await_week_index' => [

            'type' => 'input',

            'prompt'
                => 'Which canonical week_index should contain this time?',

            'expected_input' => 'week_index',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.week_index',

                'cases' => [
                    '' => 'terminal_missing_week_index',
                ],

                'default' => 'validate_parent_week',
            ],
        ],

        'validate_parent_week' => [

            'type' => 'action',

            'action' => [

                'driver' => 'db',

                'operation' => 'select_one',

                'store' => 'calendar_book_week',

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

                    'projection_id'
                        => '$context.projection.id',

                    'week_index'
                        => '$input.week_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [

                'driver' => 'boolean',

                'next' => 'await_day_index',

                'failure_state'
                    => 'terminal_parent_week_not_found',
            ],
        ],

        'await_day_index' => [

            'type' => 'input',

            'prompt'
                => 'Which canonical day_index should contain this time?',

            'expected_input' => 'day_index',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.day_index',

                'cases' => [
                    '' => 'terminal_missing_day_index',
                ],

                'default' => 'validate_parent_day',
            ],
        ],

        'validate_parent_day' => [

            'type' => 'action',

            'action' => [

                'driver' => 'db',

                'operation' => 'select_one',

                'store' => 'calendar_book_day',

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

                    'projection_id'
                        => '$context.projection.id',

                    'week_id'
                        => '$context.calendar_book_week.id',

                    'day_index'
                        => '$input.day_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [

                'driver' => 'boolean',

                'next' => 'await_time_index',

                'failure_state'
                    => 'terminal_parent_day_not_found',
            ],
        ],

        'await_time_index' => [

            'type' => 'input',

            'prompt'
                => 'What canonical time_index should be materialized?',

            'expected_input' => 'time_index',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.time_index',

                'cases' => [
                    '' => 'terminal_missing_time_index',
                ],

                'default' => 'check_existing_time',
            ],
        ],

        'check_existing_time' => [

            'type' => 'action',

            'action' => [

                'driver' => 'db',

                'operation' => 'select_one',

                'store' => 'existing_time',

                'sql' => '
                    SELECT
                        id,
                        entity_id,
                        projection_id,
                        day_id,
                        time_index
                    FROM calendar_book_times
                    WHERE projection_id = :projection_id
                      AND day_id = :day_id
                      AND time_index = :time_index
                    LIMIT 1
                ',

                'bindings' => [

                    'projection_id'
                        => '$context.projection.id',

                    'day_id'
                        => '$context.calendar_book_day.id',

                    'time_index'
                        => '$input.time_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [

                'driver' => 'boolean',

                'next'
                    => 'terminal_time_already_exists',

                'failure_state'
                    => 'await_optional_time_label_id',
            ],
        ],

        'await_optional_time_label_id' => [

            'type' => 'input',

            'prompt'
                => 'Optional time_label_id for this time? Leave blank to skip.',

            'expected_input' => 'time_label_id',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.time_label_id',

                'cases' => [
                    '' => 'await_optional_summary',
                ],

                'default' => 'await_optional_summary',
            ],
        ],

        'await_optional_summary' => [

            'type' => 'input',

            'prompt'
                => 'Optional summary for this time? Leave blank to skip.',

            'expected_input' => 'summary',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.summary',

                'cases' => [
                    '' => 'await_optional_notes',
                ],

                'default' => 'await_optional_notes',
            ],
        ],

        'await_optional_notes' => [

            'type' => 'input',

            'prompt'
                => 'Optional notes for this time? Leave blank to skip.',

            'expected_input' => 'notes',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.notes',

                'cases' => [
                    '' => 'create_book_time',
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

                    'projection_id'
                        => '$context.projection.id',

                    'week_id'
                        => '$context.calendar_book_week.id',

                    'day_id'
                        => '$context.calendar_book_day.id',

                    'time_index'
                        => '$input.time_index',

                    'time_label_id'
                        => '$input.time_label_id',

                    'summary'
                        => '$input.summary',

                    'notes'
                        => '$input.notes',
                ],
            ],

            'transition' => [

                'driver' => 'boolean',

                'next' => 'terminal_book_time_created',

                'failure_state'
                    => 'terminal_book_time_creation_failed',
            ],
        ],

        'terminal_book_time_created' => [

            'type' => 'terminal',

            'message'
                => 'Book chronology time created successfully.',
        ],

        'terminal_book_time_creation_failed' => [

            'type' => 'terminal',

            'message'
                => 'Failed to create Book chronology time.',
        ],

        'terminal_missing_projection_id' => [

            'type' => 'terminal',

            'message'
                => 'A projection_id is required.',
        ],

        'terminal_projection_not_found' => [

            'type' => 'terminal',

            'message'
                => 'Projection not found.',
        ],

        'terminal_unsupported_projection_type' => [

            'type' => 'terminal',

            'message'
                => 'This workflow supports Book projections only.',
        ],

        'terminal_missing_week_index' => [

            'type' => 'terminal',

            'message'
                => 'A positive week_index is required.',
        ],

        'terminal_parent_week_not_found' => [

            'type' => 'terminal',

            'message'
                => 'The canonical parent Book week does not exist.',
        ],

        'terminal_missing_day_index' => [

            'type' => 'terminal',

            'message'
                => 'A positive day_index is required.',
        ],

        'terminal_parent_day_not_found' => [

            'type' => 'terminal',

            'message'
                => 'The canonical parent Book day does not exist.',
        ],

        'terminal_missing_time_index' => [

            'type' => 'terminal',

            'message'
                => 'A positive time_index is required.',
        ],

        'terminal_time_already_exists' => [

            'type' => 'terminal',

            'message'
                => 'That canonical Book time already exists.',
        ],
    ],

    'initial_context' => [

        'required_projection_type_id'
            => 'projection_type_book',
    ],
];