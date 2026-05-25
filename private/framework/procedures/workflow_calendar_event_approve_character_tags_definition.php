<?php

return [
    'workflow_id' => 'calendar_event_approve_character_tags',
    'tier' => 3,
    'intent' => 'Approve ontology-backed character tags from attached prose',

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
                'next' => 'prepare_character_tag_approval',
                'failure_state' => 'terminal_calendar_event_not_found',
            ],
        ],

        'prepare_character_tag_approval' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'prepare_character_tag_approval',

                'payload' => [
                    'calendar_event_entity_id'
                        => '$context.calendar_event.entity_id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_character_tag_approval',
                'failure_state' => 'terminal_no_resolved_character_suggestions',
            ],
        ],

        'await_character_tag_approval' => [
            'type' => 'input',
            'prompt' => 'Approve the resolved character suggestions? Reply yes or no.',
            'expected_input' => 'character_tag_approval',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.character_tag_approval',
                'cases' => [
                    '' => 'await_character_tag_approval',
                ],
                'default' => 'apply_character_tags',
            ],
        ],

        'apply_character_tags' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'apply_character_tags',
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_character_tags_applied',
                'failure_state' => 'terminal_character_tag_approval_rejected',
            ],
        ],

        'terminal_character_tags_applied' => [
            'type' => 'terminal',
            'message' => 'Character tags approved and applied to calendar_event_participants.',
        ],

        'terminal_character_tag_approval_rejected' => [
            'type' => 'terminal',
            'message' => 'Character tag approval was rejected. No participant links were created.',
        ],

        'terminal_no_resolved_character_suggestions' => [
            'type' => 'terminal',
            'message' => 'No resolved ontology-backed character suggestions were available to approve.',
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