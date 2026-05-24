<?php

return [
    'workflow_id' => 'calendar_event_derive_beat_title',
    'tier' => 3,
    'intent' => 'Derive beat and title from prose attached to a calendar event',

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
                       OR entity_id = CONCAT('calendar_event:', :entity_id)
                    LIMIT 1
                ",

                'bindings' => [
                    'entity_id' => '$input.calendar_event_entity_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'derive_beat_title',
                'failure_state' => 'terminal_calendar_event_not_found',
            ],
        ],

        'derive_beat_title' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'derive_beat_title',

                'payload' => [
                    'calendar_event_entity_id'
                        => '$context.calendar_event.entity_id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_beat_title_derived',
                'failure_state' => 'terminal_beat_title_derivation_failed',
            ],
        ],

        'terminal_beat_title_derived' => [
            'type' => 'terminal',

            'message' =>
                'Beat and title derived from attached prose.',

            'response' => [
                'summary' => 'Beat and title derived from attached prose. These values are proposed metadata and have not been applied to the calendar event yet.',

                'derived' => '$context.beat_title_derivation',

                'next_chat_handoff' => [
                    'workflow_id' => 'calendar_event_apply_beat_title',
                    'message' => 'Apply the derived beat and title metadata to this calendar event.',
                    'canonical_context' => [
                        'calendar_event_entity_id'
                            => '$context.beat_title_derivation.calendar_event_entity_id',

                        'prose_entity_id'
                            => '$context.beat_title_derivation.prose_entity_id',

                        'prose_projection_id'
                            => '$context.beat_title_derivation.prose_projection_id',
                    ],
                    'proposed_calendar_event_metadata' => '$context.beat_title_derivation.proposed_calendar_event_metadata',
                ],

                'future_chat_handoff' => [
                    'workflow_id' => 'calendar_event_process_attached_prose',
                    'message' => 'Optionally segment the attached prose into calendar subevents later.',
                    'canonical_context' => [
                        'calendar_event_entity_id'
                            => '$context.beat_title_derivation.calendar_event_entity_id',
                    ],
                ],
            ],

            'handoff_packet' => '$context.handoff_packet',
        ],

        'terminal_missing_entity_id' => [
            'type' => 'terminal',
            'message' => 'A calendar_event entity_id is required.',
        ],

        'terminal_calendar_event_not_found' => [
            'type' => 'terminal',
            'message' => 'No calendar_event found for that entity_id.',
        ],

        'terminal_beat_title_derivation_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to derive beat and title from attached prose.',
        ],
    ],
];
