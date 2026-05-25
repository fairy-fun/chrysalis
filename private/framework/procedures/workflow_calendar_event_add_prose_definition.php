<?php

return [
    'workflow_id' => 'calendar_event_add_prose',
    'tier' => 3,
    'intent' => 'Add prose to an existing calendar_event through prose-family-aware projection topology',

    'initial_context' => [
        'workflow_doctrine' => [
            'prose_family_is_container' => true,
            'drafts_are_sibling_variants' => true,
            'draft_creation_is_not_publication' => true,
            'projection_controls_export_authority' => true,
            'reference_labels_are_retrieval_affordances' => true,
        ],
    ],

    'entry_state' => 'await_calendar_event_entity_id',

    'states' => [

        'await_calendar_event_entity_id' => [
            'type' => 'input',
            'prompt' => 'What is the existing calendar event? You can enter calendar_event:5, 5, a canonical event entity_id, or chronology notation like 1.2.1.3.',
            'expected_input' => 'calendar_event_entity_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.calendar_event_entity_id',
                'cases' => [
                    '' => 'terminal_missing_entity_id',
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
                        => '$input.calendar_event_entity_id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_prose_text',
                'failure_state' => 'await_prose_text',
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
                'operation' => 'create_calendar_event_family_draft',

                'payload' => [
                    'entity_id' => 'prose:' . uniqid(),
                    'calendar_event_entity_id'
                        => '$context.calendar_event.entity_id',
                    'title' => 'Workflow prose draft',
                    'prose_body' => '$input.prose',
                    'draft_status_id' => 'prose_status_draft',
                    'projection_classval_id'
                        => 'projection_type_timeline_view',
                    'projection_type_id'
                        => 'projection_type_timeline_view',
                    'role_id'
                        => 'prose_projection_role_primary',
                    'projection_order' => 1,
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_optional_reference_label',
                'failure_state' => 'terminal_prose_persist_failed',
            ],
        ],

        'await_optional_reference_label' => [
            'type' => 'input',
            'prompt' => 'Optional: enter a short lookup label for this event (for example: 20250121-z). Leave blank to skip.',
            'expected_input' => 'reference_label',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.reference_label',
                'cases' => [
                    '' => 'terminal_prose_created',
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
                        => '$context.created_prose.prose_family.entity_id',

                    'calendar_event_id'
                        => '$context.calendar_event.id',

                    'reference_label'
                        => '$input.reference_label',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_prose_created',
                'failure_state' => 'terminal_reference_label_failed',
            ],
        ],

        'terminal_prose_created' => [
            'type' => 'terminal',

            'message' => 'Prose draft created successfully within prose-family-aware projection topology.',

            'response' => [
                'summary' => 'Prose attached successfully. Draft creation did not mutate publication authority or export authority.',

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

                        'prose_family_entity_id'
                            => '$context.created_prose.prose_family.entity_id',
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

                    'prose_family_entity_id'
                        => '$context.created_prose.prose_family.entity_id',
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

        'terminal_reference_label_failed' => [
            'type' => 'terminal',
            'message' => 'Prose draft was created, but reference label persistence failed.',
        ],

        'terminal_missing_entity_id' => [
            'type' => 'terminal',
            'message' => 'A calendar_event reference is required.',
        ],
    ],
];