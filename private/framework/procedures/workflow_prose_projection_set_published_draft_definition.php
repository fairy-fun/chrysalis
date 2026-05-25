<?php

return [
    'workflow_id' => 'prose_projection_set_published_draft',

    'tier' => 3,

    'intent' => 'Set the canonical published prose draft for an export projection',

    'initial_context' => [
        'workflow_doctrine' => [
            'projection_controls_publication' => true,
            'drafts_are_sibling_variants' => true,
            'draft_lineage_is_forbidden' => true,
            'chronology_is_not_canonicality' => true,
            'publication_is_projection_contextual' => true,
        ],
    ],

    'entry_state' => 'await_calendar_event_reference',

    'states' => [

        'await_calendar_event_reference' => [
            'type' => 'input',
            'prompt' => 'What calendar event export projection should be updated? You can enter calendar_event:3, 3, or 1.2.1.3.',
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
                'next' => 'await_draft_id',
                'failure_state' => 'terminal_projection_not_found',
            ],
        ],

        'await_draft_id' => [
            'type' => 'input',
            'prompt' => 'Enter the prose_drafts.id to elevate as the canonical export draft for this projection.',
            'expected_input' => 'published_prose_draft_id',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'set_projection_published_draft',
            ],
        ],

        'set_projection_published_draft' => [
            'type' => 'action',

            'action' => [
                'driver' => 'prose',
                'operation' => 'set_projection_published_draft',

                'payload' => [
                    'projection_id' => '$context.prose_family.prose_projection_id',
                    'published_prose_draft_id' => '$input.published_prose_draft_id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_publication_updated',
                'failure_state' => 'terminal_publication_update_failed',
            ],
        ],

        'terminal_publication_updated' => [
            'type' => 'terminal',
            'message' => 'Canonical export publication updated successfully for the prose projection.',
        ],

        'terminal_projection_not_found' => [
            'type' => 'terminal',
            'message' => 'No export projection could be resolved for that calendar event.',
        ],

        'terminal_publication_update_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to update canonical export publication for projection.',
        ],
    ],
];
