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

    'entry_state' => 'await_prose_family_entity_id',

    'states' => [

        'await_prose_family_entity_id' => [
            'type' => 'input',
            'prompt' => 'What is the prose family entity ID?',
            'expected_input' => 'prose_family_entity_id',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'validate_prose_family',
            ],
        ],

        'validate_prose_family' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'prose_family',

                'sql' => '
                    SELECT
                        id,
                        entity_id
                    FROM prose_families
                    WHERE entity_id = :entity_id
                    LIMIT 1
                ',

                'bindings' => [
                    'entity_id' => '$input.prose_family_entity_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_prose_text',
                'failure_state' => 'terminal_prose_family_not_found',
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
                    'prose_family_entity_id' => '$context.prose_family.entity_id',
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

            'message' => 'Sibling prose draft created successfully within the prose family.',
        ],

        'terminal_prose_family_not_found' => [
            'type' => 'terminal',
            'message' => 'No prose family found for that entity ID.',
        ],

        'terminal_draft_creation_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to create prose draft in prose family.',
        ],
    ],
];
