<?php

return [
    'workflow_id' => 'calendar_event_approve_location_tags',
    'tier' => 3,
    'intent' => 'Approve ontology-backed location tags from attached prose',

    'entry_state' => 'await_calendar_event_entity_id',

    'states' => [

        'await_calendar_event_entity_id' => [
            'type' => 'input',
            'prompt' => 'What is the calendar event entity ID?',
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

                'sql' => "
                    SELECT
                        id,
                        entity_id,
                        layer_id,
                        projection_id,
                        book_time_id,
                        event_index,
                        subevent_index,
                        sequence_index
                    FROM calendar_events
                    WHERE entity_id = :entity_id
                       OR entity_id = CONCAT('calendar_event:', :bare_entity_id)
                    LIMIT 1
                ",

                'bindings' => [
                    'entity_id' => '$input.calendar_event_entity_id',
                    'bare_entity_id' => '$input.calendar_event_entity_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'prepare_location_tag_approval',
                'failure_state' => 'terminal_calendar_event_not_found',
            ],
        ],

        'prepare_location_tag_approval' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'prepare_location_tag_approval',

                'payload' => [
                    'calendar_event_entity_id'
                        => '$context.calendar_event.entity_id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_location_tag_approval',
                'failure_state' => 'terminal_no_resolved_location_suggestions',
            ],
        ],

        'await_location_tag_approval' => [
            'type' => 'input',
            'prompt' => 'Approve the resolved location suggestions? Reply yes, no, entity IDs to approve, or exclusions such as all except PLACE-013.',
            'expected_input' => 'location_tag_approval',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.location_tag_approval',
                'cases' => [
                    '' => 'await_location_tag_approval',
                ],
                'default' => 'apply_location_tags',
            ],
        ],

        'apply_location_tags' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'apply_location_tags',
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_location_tags_applied',
                'failure_state' => 'terminal_location_tag_approval_rejected',
            ],
        ],

        'terminal_location_tags_applied' => [
            'type' => 'terminal',
            'message' => 'Location tags approved and applied to calendar_events.',
        ],

        'terminal_location_tag_approval_rejected' => [
            'type' => 'terminal',
            'message' => 'Location tag approval was rejected or no approved subset remained. No location links were created.',
        ],

        'terminal_no_resolved_location_suggestions' => [
            'type' => 'terminal',
            'message' => 'No resolved ontology-backed location suggestions were available to approve.',
        ],

        'terminal_calendar_event_not_found' => [
            'type' => 'terminal',
            'message' => 'No calendar_event found for that entity_id.',
        ],

        'terminal_missing_entity_id' => [
            'type' => 'terminal',
            'message' => 'A calendar event entity ID is required.',
        ],

    ],
];