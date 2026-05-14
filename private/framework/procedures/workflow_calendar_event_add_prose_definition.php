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
            'failure_state' => 'terminal_calendar_event_not_found',

            'next' => 'enforce_calendar_event_layer',
        ],

        'enforce_calendar_event_layer' => [
            'type' => 'action',

            'assert' => [
                'left' => '$row.layer_id',
                'operator' => 'equals',
                'right' => 'calendar_layer_event',
            ],

            'failure_state' => 'terminal_wrong_layer',

            'next' => 'await_projection_binding',
        ],

        'await_projection_binding' => [
            'type' => 'input',
            'prompt' => 'Which projection should the prose be added to?',
            'expected_input' => 'projection_id',
        ],

        'terminal_calendar_event_not_found' => [
            'type' => 'terminal',
            'message' => 'No calendar_event found for that entity_id.',
        ],

        'terminal_wrong_layer' => [
            'type' => 'terminal',
            'message' => 'Entity exists but is not calendar_layer_event.',
        ],
    ],
];