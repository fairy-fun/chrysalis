<?php

return [
    'workflow_id' => 'prose_family_add_draft',

    'tier' => 3,

    'intent' => 'Add a new sibling prose draft to an existing prose family',

    'initial_context' => [
        'workflow_doctrine' => [
            'prose_family_is_container' => true,
            'drafts_are_sibling_variants' => true,
            'draft_lineage_is_forbidden' => true,
            'draft_creation_is_not_publication' => true,
            'draft_creation_is_not_export_authority' => true,
            'projection_controls_export_authority' => true,
        ],
    ],

    'entry_state' => 'await_calendar_event_reference',

    'states' => [

        'await_calendar_event_reference' => [
            'type' => 'input',
            'prompt' => 'What calendar event is this prose attached to? You can enter calendar_event:3, 3, or 1.2.1.3.',
            'expected_input' => 'calendar_event_reference',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'resolve_calendar_event_family',
            ],
        ],

        'resolve_calendar_event_family' => [
            'type' => 'action',

            'action' => [
                'driver' => 'prose',
                'operation' => 'resolve_calendar_event_family',

                'payload' => [
                    'calendar_event_reference' => '$input.calendar_event_reference',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_prose_text',
                'failure_state' => 'terminal_calendar_event_family_not_found',
            ],
        ],

        'await_prose_text' => [
            'type' => 'input',
            'prompt' => 'Enter the prose draft text.',
            'expected_input' => 'prose_body',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'create_family_draft',
            ],
        ],

        'create_family_draft' => [
            'type' => 'action',

            'action' => [
                'driver' => 'prose',
                'operation' => 'create_family_draft',

                'payload' => [
                    'entity_id' => 'prose:' . uniqid(),
                    'prose_family_id' => '$context.prose_family.id',
                    'title' => 'Workflow prose draft',
                    'prose_body' => '$input.prose_body',
                    'draft_status_id' => 'prose_status_draft',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_draft_created',
                'failure_state' => 'terminal_draft_creation_failed',
            ],
        ],

        'terminal_draft_created' => [
            'type' => 'terminal',

            'message' => 'Sibling prose draft created successfully within the prose family. No projection publication or export authority was changed.',
        ],

        'terminal_calendar_event_family_not_found' => [
            'type' => 'terminal',
            'message' => 'No attached prose family could be resolved for that calendar event.',
        ],

        'terminal_draft_creation_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to create prose draft in prose family.',
        ],
    ],
];
