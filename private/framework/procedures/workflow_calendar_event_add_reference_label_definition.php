<?php

return [
    'workflow_id' => 'calendar_event_add_reference_label',
    'tier' => 2,
    'intent' => 'Add a human-authored reference label to an existing calendar-event prose attachment',

    'initial_context' => [
        'workflow_doctrine' => [
            'reference_labels_are_retrieval_affordances' => true,
            'reference_labels_are_not_event_identity' => true,
            'reference_labels_are_not_global' => true,
            'calendar_event_must_already_have_prose_topology' => true,
            'draft_creation_is_forbidden' => true,
            'publication_mutation_is_forbidden' => true,
            'export_authority_mutation_is_forbidden' => true,
        ],
    ],

    'entry_state' => 'await_calendar_event_reference',

    'states' => [

        'await_calendar_event_reference' => [
            'type' => 'input',
            'prompt' => 'Which existing calendar event already has prose attached? You can enter calendar_event:5, 5, a canonical event entity_id, or chronology notation like 1.2.1.3.',
            'expected_input' => 'calendar_event_reference',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.calendar_event_reference',
                'cases' => [
                    '' => 'terminal_missing_calendar_event_reference',
                ],
                'default' => 'resolve_calendar_event_family',
            ],
        ],

        'resolve_calendar_event_family' => [
            'type' => 'action',

            'action' => [
                'driver' => 'prose',
                'operation' => 'resolve_calendar_event_family',

                'payload' => [
                    'calendar_event_reference'
                        => '$input.calendar_event_reference',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'require_existing_prose_family',
                'failure_state' => 'terminal_calendar_event_not_found',
            ],
        ],

        'require_existing_prose_family' => [
            'type' => 'action',

            'assert' => [
                'left' => '$context.prose_family.id',
                'operator' => 'is_not_null',
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_reference_label',
                'failure_state' => 'terminal_no_existing_prose_attachment',
            ],
        ],

        'await_reference_label' => [
            'type' => 'input',
            'prompt' => 'Enter the short lookup label to add for this prose attachment (for example: 20250121-z).',
            'expected_input' => 'reference_label',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.reference_label',
                'cases' => [
                    '' => 'terminal_missing_reference_label',
                ],
                'default' => 'persist_reference_label',
            ],
        ],

        'persist_reference_label' => [
            'type' => 'action',

            'action' => [
                'driver' => 'prose',
                'operation' => 'upsert_calendar_event_reference_label',

                'payload' => [
                    'prose_family_entity_id'
                        => '$context.prose_family.entity_id',

                    'calendar_event_id'
                        => '$context.calendar_event.id',

                    'reference_label'
                        => '$input.reference_label',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_reference_label_created',
                'failure_state' => 'terminal_reference_label_failed',
            ],
        ],

        'terminal_reference_label_created' => [
            'type' => 'terminal',
            'message' => 'Reference label added successfully.',

            'response' => [
                'summary' => 'Reference label added to the existing prose attachment. No prose draft, publication authority, or export authority was changed.',

                'canonical_context' => [
                    'calendar_event_entity_id'
                        => '$context.calendar_event.entity_id',

                    'prose_family_entity_id'
                        => '$context.prose_family.entity_id',

                    'reference_label'
                        => '$input.reference_label',
                ],
            ],
        ],

        'terminal_calendar_event_not_found' => [
            'type' => 'terminal',
            'message' => 'No calendar event found for that reference.',
        ],

        'terminal_no_existing_prose_attachment' => [
            'type' => 'terminal',
            'message' => 'That calendar event does not yet have an existing prose attachment. Use the add-prose workflow first.',
        ],

        'terminal_missing_calendar_event_reference' => [
            'type' => 'terminal',
            'message' => 'A calendar event reference is required.',
        ],

        'terminal_missing_reference_label' => [
            'type' => 'terminal',
            'message' => 'A reference label is required for this workflow.',
        ],

        'terminal_reference_label_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to add reference label.',
        ],
    ],
];