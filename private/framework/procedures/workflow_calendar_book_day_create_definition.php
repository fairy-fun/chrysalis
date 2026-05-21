<?php

return [

    'workflow_id' => 'calendar_book_day_create',

    /*
    |--------------------------------------------------------------------------
    | Ontology tier
    |--------------------------------------------------------------------------
    |
    | This workflow performs canonical Book chronology-container authoring.
    |
    | It is NOT Tier 1 event creation.
    |
    | It materializes:
    |
    |   calendar_book_days
    |
    */

    'tier' => 0,

    'intent'
        => 'Create a canonical Book chronology day container',

    'entry_state' => 'await_projection_id',

    'states' => [

        /*
        |--------------------------------------------------------------------------
        | Projection selection
        |--------------------------------------------------------------------------
        */

        'await_projection_id' => [

            'type' => 'input',

            'prompt'
                => 'Which projection should this Book day belong to?',

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

        /*
        |--------------------------------------------------------------------------
        | Projection ontology validation
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Canonical parent week locality
        |--------------------------------------------------------------------------
        */

        'await_week_index' => [

            'type' => 'input',

            'prompt'
                => 'Which canonical week_index should contain this day?',

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

        /*
        |--------------------------------------------------------------------------
        | Parent containment validation
        |--------------------------------------------------------------------------
        |
        | Day creation requires an existing canonical week container.
        |
        | Tier 0 may materialize days.
        | Tier 0 must NOT infer or create missing weeks here.
        |
        */

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

        /*
        |--------------------------------------------------------------------------
        | Canonical day locality
        |--------------------------------------------------------------------------
        */

        'await_day_index' => [

            'type' => 'input',

            'prompt'
                => 'What canonical day_index should be materialized?',

            'expected_input' => 'day_index',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.day_index',

                'cases' => [
                    '' => 'terminal_missing_day_index',
                ],

                'default' => 'check_existing_day',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Existing chronology protection
        |--------------------------------------------------------------------------
        |
        | Duplicate canonical day locality is forbidden.
        |
        */

        'check_existing_day' => [

            'type' => 'action',

            'action' => [

                'driver' => 'db',

                'operation' => 'select_one',

                'store' => 'existing_day',

                'sql' => '
                    SELECT
                        id,
                        projection_id,
                        week_id,
                        day_index,
                        entity_id
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

                'next'
                    => 'terminal_day_already_exists',

                'failure_state'
                    => 'await_day_of_week_id',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Canonical day identity
        |--------------------------------------------------------------------------
        */

        'await_day_of_week_id' => [

            'type' => 'input',

            'prompt'
                => 'What day_of_week_id should this canonical day use?',

            'expected_input' => 'day_of_week_id',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.day_of_week_id',

                'cases' => [
                    '' => 'terminal_missing_day_of_week_id',
                ],

                'default' => 'await_optional_summary',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Optional metadata
        |--------------------------------------------------------------------------
        */

        'await_optional_summary' => [

            'type' => 'input',

            'prompt'
                => 'Optional summary for this day? Leave blank to skip.',

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
                => 'Optional notes for this day? Leave blank to skip.',

            'expected_input' => 'notes',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.notes',

                'cases' => [
                    '' => 'create_book_day',
                ],

                'default' => 'create_book_day',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Canonical chronology authoring
        |--------------------------------------------------------------------------
        */

        'create_book_day' => [

            'type' => 'action',

            'action' => [

                'driver' => 'calendar',

                'operation' => 'create_book_day',

                'payload' => [

                    'projection_id'
                        => '$context.projection.id',

                    'week_id'
                        => '$context.calendar_book_week.id',

                    'week_index'
                        => '$context.calendar_book_week.week_index',

                    'day_index'
                        => '$input.day_index',

                    'day_of_week_id'
                        => '$input.day_of_week_id',

                    'summary'
                        => '$input.summary',

                    'notes'
                        => '$input.notes',
                ],
            ],

            'transition' => [

                'driver' => 'boolean',

                'next' => 'terminal_book_day_created',

                'failure_state'
                    => 'terminal_book_day_creation_failed',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Terminal states
        |--------------------------------------------------------------------------
        */

        'terminal_book_day_created' => [

            'type' => 'terminal',

            'message'
                => 'Book chronology day created successfully.',
        ],

        'terminal_book_day_creation_failed' => [

            'type' => 'terminal',

            'message'
                => 'Failed to create Book chronology day.',
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

        'terminal_missing_day_of_week_id' => [

            'type' => 'terminal',

            'message'
                => 'A day_of_week_id is required.',
        ],

        'terminal_day_already_exists' => [

            'type' => 'terminal',

            'message'
                => 'That canonical Book day already exists.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial ontology context
    |--------------------------------------------------------------------------
    */

    'initial_context' => [

        'required_projection_type_id'
            => 'projection_type_book',
    ],
];