<?php

return [
    'workflow_id' => 'calendar_event_add_prose',
    'tier' => 2,
    'intent' => 'Add prose to an existing calendar_event',

    'entry_state' => 'await_calendar_event_entity_id',

    'states' => [

        'await_calendar_event_entity_id' => [
            'type' => 'input',
            'prompt' => 'What is the existing calendar event? You can enter calendar_event:5, 5, or a supported event entity_id.',
            'expected_input' => 'calendar_event_entity_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.calendar_event_entity_id',
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
                        projection_id,
                        book_time_id,
                        event_index
                    FROM calendar_events
                    WHERE entity_id = :entity_id
                       OR entity_id = CONCAT(\'calendar_event:\', :entity_id)
                    LIMIT 1
                ',

                'bindings' => [
                    'entity_id' => '$input.calendar_event_entity_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'inspect_calendar_event_prose',
                'failure_state' => 'terminal_calendar_event_not_found',
            ],
        ],

        'inspect_calendar_event_prose' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'calendar_event_prose',

                'sql' => '
                    SELECT
                        prose_body,
                        notes
                    FROM calendar_events
                    WHERE entity_id = :entity_id
                    AND (
                        prose_body IS NOT NULL
                        OR notes IS NOT NULL
                    )
                    LIMIT 1
                ',

                'bindings' => [
                    'entity_id' => '$context.calendar_event.entity_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_calendar_event_already_has_prose',
                'failure_state' => 'inspect_calendar_subevent_prose',
            ],
        ],

        'inspect_calendar_subevent_prose' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'calendar_subevent_prose',

                'sql' => '
                    SELECT
                        id,
                        entity_id,
                        prose_body,
                        notes
                    FROM calendar_events
                    WHERE parent_event_id = :event_id
                    AND (
                        prose_body IS NOT NULL
                        OR notes IS NOT NULL
                    )
                    LIMIT 1
                ',

                'bindings' => [
                    'event_id' => '$context.calendar_event.id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_calendar_subevent_has_prose',
                'failure_state' => 'route_calendar_event_layer',
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
                    'calendar_layer_event' => 'await_prose_text',
                    'calendar_layer_subevent' => 'await_prose_text',
                ],

                'default' => 'terminal_wrong_layer',
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
                    'entity_id' => 'prose:' . uniqid(),
                    'title' => 'Workflow prose draft',
                    'prose_body' => '$input.prose',
                    'draft_status_id' => 'prose_status_draft',

                    'projection' => [
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

        'terminal_prose_created' => [
            'type' => 'terminal',

            'message' => 'Prose draft created successfully.',

            'response' => [
                'summary' => 'Prose attached successfully. The next chat should continue with prose processing and segmentation.',

                'next_chat_handoff' => [
                    'workflow_id' => 'calendar_event_process_attached_prose',

                    'message' => 'Continue processing attached prose for this calendar event.',

                    'canonical_context' => [
                        'calendar_event_entity_id'
                            => '$context.calendar_event.entity_id',

                        'prose_entity_id'
                            => '$context.created_prose.prose.entity_id',

                        'prose_projection_id'
                            => '$context.created_prose.projection.id',
                    ],
                ],
            ],

            'handoff_packet' => [
                'workflow_stage' => 'prose_attached',

                'canonical' => [
                    'calendar_event_entity_id'
                        => '$context.calendar_event.entity_id',

                    'prose_entity_id'
                        => '$context.created_prose.prose.entity_id',

                    'prose_projection_id'
                        => '$context.created_prose.projection.id',
                ],

                'next_workflow' => [
                    'workflow_id'
                        => 'calendar_event_process_attached_prose',

                    'initial_context' => [
                        'calendar_event_entity_id'
                            => '$context.calendar_event.entity_id',
                    ],
                ],
            ],
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
            'message' => 'No calendar_event found for that reference.',
        ],

        'terminal_calendar_event_already_has_prose' => [
            'type' => 'terminal',
            'message' => 'This calendar event already has prose attached.',
        ],

        'terminal_calendar_subevent_has_prose' => [
            'type' => 'terminal',
            'message' => 'A subevent under this calendar event already has prose attached.',
        ],

        'terminal_wrong_layer' => [
            'type' => 'terminal',
            'message' => 'Entity exists but is not a supported prose layer.',
        ],
    ],
];
