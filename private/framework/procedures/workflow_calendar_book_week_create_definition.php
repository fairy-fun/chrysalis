<?php

return [

    'workflow_id' => 'calendar_book_week_create',

    /*
    |--------------------------------------------------------------------------
    | Ontology tier
    |--------------------------------------------------------------------------
    |
    | This workflow performs chronology-container authoring.
    |
    | It is NOT Tier 1 event creation.
    |
    */

    'tier' => 0,

    'intent'
        => 'Create a canonical Book chronology week container',

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
                => 'Which projection should this Book week belong to?',

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
        | Week locality
        |--------------------------------------------------------------------------
        */

        'await_week_index' => [

            'type' => 'input',

            'prompt'
                => 'What canonical week_index should be materialized?',

            'expected_input' => 'week_index',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.week_index',

                'cases' => [
                    '' => 'terminal_missing_week_index',
                ],

                'default' => 'check_existing_week',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Existing chronology protection
        |--------------------------------------------------------------------------
        |
        | Chronology containers are canonical ontology structures.
        | Duplicate week_index within a projection is forbidden.
        |
        */

        'check_existing_week' => [

            'type' => 'action',

            'action' => [

                'driver' => 'db',

                'operation' => 'select_one',

                'store' => 'existing_week',

                'sql' => '
                    SELECT
                        id,
                        projection_id,
                        week_index,
                        entity_id
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

                'next'
                    => 'terminal_week_already_exists',

                'failure_state'
                    => 'await_optional_summary',
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
                => 'Optional summary for this week? Leave blank to skip.',

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
                => 'Optional notes for this week? Leave blank to skip.',

            'expected_input' => 'notes',

            'transition' => [

                'driver' => 'match',

                'value' => '$input.notes',

                'cases' => [
                    '' => 'create_book_week',
                ],

                'default' => 'create_book_week',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Canonical chronology authoring
        |--------------------------------------------------------------------------
        */

        'create_book_week' => [

            'type' => 'action',

            'action' => [

                'driver' => 'calendar',

                'operation' => 'create_book_week',

                'payload' => [

                    'projection_id'
                        => '$context.projection.id',

                    'week_index'
                        => '$input.week_index',

                    'summary'
                        => '$input.summary',

                    'notes'
                        => '$input.notes',
                ],
            ],

            'transition' => [

                'driver' => 'boolean',

                'next' => 'terminal_book_week_created',

                'failure_state'
                    => 'terminal_book_week_creation_failed',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Terminal states
        |--------------------------------------------------------------------------
        */

        'terminal_book_week_created' => [

            'type' => 'terminal',

            'message'
                => 'Book chronology week created successfully.',
        ],

        'terminal_book_week_creation_failed' => [

            'type' => 'terminal',

            'message'
                => 'Failed to create Book chronology week.',
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

        'terminal_week_already_exists' => [

            'type' => 'terminal',

            'message'
                => 'That canonical Book week already exists.',
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