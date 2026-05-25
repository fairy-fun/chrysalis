<?php

return [

    'workflow_id' => 'prose_reference_label_display',

    'tier' => 4,

    'intent' => 'Show the published prose for a prose-family scoped human reference label',

    'initial_context' => [
        'workflow_doctrine' => [
            'reference_labels_are_retrieval_affordances' => true,
            'reference_labels_are_not_event_identity' => true,
            'reference_labels_are_not_global' => true,
            'display_uses_explicit_publication_authority' => true,
            'latest_draft_fallback_is_forbidden' => true,
            'draft_creation_is_forbidden' => true,
            'publication_mutation_is_forbidden' => true,
            'export_authority_mutation_is_forbidden' => true,
        ],
    ],

    'entry_state' => 'await_reference_label',

    'states' => [

        'await_reference_label' => [
            'type' => 'input',
            'prompt' => 'Which prose reference label should be displayed? For example: 20250120-a.',
            'expected_input' => 'reference_label',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.reference_label',
                'cases' => [
                    '' => 'terminal_missing_reference_label',
                ],
                'default' => 'load_prose_by_reference_label',
            ],
        ],

        'load_prose_by_reference_label' => [
            'type' => 'action',

            'action' => [
                'driver' => 'prose',
                'operation' => 'display_by_reference_label',

                'payload' => [
                    'reference_label' => '$input.reference_label',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_reference_label_prose',
                'failure_state' => 'terminal_no_reference_label_prose_found',
            ],
        ],

        'terminal_reference_label_prose' => [
            'type' => 'terminal',
            'message' => 'Reference-label prose retrieved.',

            'response' => [
                'answer' => '$context.artifact.assembled_prose',
                'display_instruction' => 'Display answer as the continuous prose body. Do not summarise it. The reference label is a retrieval affordance only, not canonical event identity.',
                'artifact' => '$context.artifact',
            ],
        ],

        'terminal_missing_reference_label' => [
            'type' => 'terminal',
            'message' => 'A prose reference label is required.',
        ],

        'terminal_no_reference_label_prose_found' => [
            'type' => 'terminal',
            'message' => 'No published prose found for that reference label.',
        ],
    ],
];
