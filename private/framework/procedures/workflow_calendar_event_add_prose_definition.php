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
            'next' => 'validate_calendar_event_entity',
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
                ',

                'bindings' => [
                    'entity_id' => '$input.entity_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'match',

                'value' => '$context.calendar_event.layer_id',

                'cases' => [
                    'calendar_layer_event' => 'await_projection_binding',
                    'calendar_layer_note' => 'await_prose_text',
                    'calendar_layer_annotation' => 'await_prose_text',
                ],

                'default' => 'terminal_wrong_layer',
            ],
        ],

        'await_projection_binding' => [
            'type' => 'input',
            'prompt' => 'Which projection should the prose be added to?',
            'expected_input' => 'projection_id',
            'next' => 'validate_projection_binding',
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
            'next' => 'terminal_ready_for_persistence',
        ],

        'terminal_projection_mismatch' => [
            'type' => 'terminal',
            'message' => 'Projection does not match calendar_event projection.',
        ],

        'terminal_ready_for_persistence' => [
            'type' => 'terminal',
            'message' => 'Workflow validated and ready for prose persistence.',
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