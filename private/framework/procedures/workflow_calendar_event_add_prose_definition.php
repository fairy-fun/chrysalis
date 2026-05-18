<?php

return [
    'workflow_id' => 'calendar_event_add_prose',
    'tier' => 2,
    'intent' => 'Add prose to an existing calendar_event',

    'initial_state' => 'await_calendar_event_entity_id',

    'states' => [

        'await_calendar_event_entity_id' => [
            'type' => 'input',

            'prompt' => 'Enter the calendar_event entity_id.',
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
                'next' => 'route_calendar_event_layer',
                'failure_state' => 'terminal_calendar_event_not_found',
            ],
        ],

        'route_calendar_event_layer' => [
            'type' => 'action',

            'assert' => [
                'left' => '$context.calendar_event.layer_id',
                'operator' => 'is_not_null',
            ],

            'transition' => [
                'driver' => 'match',

                'value' => '$context.calendar_event.layer_id',

                'cases' => [
                    'calendar_layer_event' => 'await_projection_binding',
                    'calendar_layer_subevent' => 'await_prose_text',
                ],

                'default' => 'terminal_wrong_layer',
            ],
        ],

        'await_projection_binding' => [
            'type' => 'input',

            'prompt' => 'Which projection should the prose be added to?',
            'expected_input' => 'projection_id',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'validate_projection_binding',
            ],
        ],

        'validate_projection_binding' => [
            'type' => 'action',

            'assert' => [
                'left' => '$input.projection_id',
                'operator' => 'equals',
                'right' => '$context.calendar_event.projection_id',
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_prose_text',
                'failure_state' => 'terminal_projection_mismatch',
            ],
        ],

        'await_prose_text' => [
            'type' => 'input',

            'prompt' => 'Enter the prose text.',
            'expected_input' => 'prose',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'persist_prose_draft',
            ],
        ],

        'persist_prose_draft' => [
            'type' => 'action',

            'action' => [
                'driver' => 'prose',
                'operation' => 'create_draft',

                'payload' => [

                    // Replace with your actual entity allocation strategy.
                    'entity_id' => 'prose:' . uniqid(),

                    'title' => 'Workflow prose draft',

                    'prose_body' => '$input.prose',

                    'draft_status_id' => 'prose_status_draft',

                    'projection' => [

                        // We will likely replace this once projection
                        // classval semantics stabilize.
                        'projection_classval_id' => 'projection_type_timeline_view',

                        'projection_type_id' => 'projection_type_timeline_view',

                        'target_entity_id' => '$context.calendar_event.entity_id',

                        'role_id' => 'prose_projection_role_primary',

                        'projection_order' => 1,
                    ],
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_prose_created',
                'failure_state' => 'terminal_prose_persist_failed',
            ],
        ],

        'terminal_projection_mismatch' => [
            'type' => 'terminal',
            'message' => 'Projection does not match calendar_event projection.',
        ],

        'terminal_prose_created' => [
            'type' => 'terminal',
            'message' => 'Prose draft created successfully.',
        ],

        'terminal_prose_persist_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to persist prose draft.',
        ],

        'terminal_missing_entity_id' => [
            'type' => 'terminal',
            'message' => 'A calendar_event entity_id is required.',
        ],

        'terminal_calendar_event_not_found' => [
            'type' => 'terminal',
            'message' => 'No calendar_event found for that entity_id.',
        ],

        'terminal_wrong_layer' => [
            'type' => 'terminal',
            'message' => 'Entity exists but is not a supported prose layer.',
        ],
    ],
];