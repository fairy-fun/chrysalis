<?php

return [

    'workflow_id' => 'calendar_week_display_prose',

    'tier' => 1,

    'intent' => 'Show the existing prose for a canonical Book week',

    'entry_state' => 'await_week_input',

    'initial_context' => [
        'projection' => [
            'id' => 1,
            'projection_code' => 'book_projection_BOOK-001',
        ],
    ],

    'states' => [

        'await_week_input' => [

            'type' => 'input',

            'prompt' =>
                'Which Book week should be displayed?',

            'expected_input' => 'week',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.week',

                'cases' => [
                    '' => 'terminal_missing_week',
                ],

                'default' => 'load_week_prose',
            ],
        ],

        'load_week_prose' => [

            'type' => 'action',

            'action' => [

                'driver' => 'calendar',

                'operation' => 'display_week_prose',

                'payload' => [
                    'week' => '$input.week',
                    'projection_id' => '$context.projection.id',
                    'prose_mode' => '$input.prose_mode',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_week_prose',
                'failure_state' => 'terminal_no_week_prose_found',
            ],
        ],

        'terminal_week_prose' => [

            'type' => 'terminal',

            'message' =>
                'Canonical Book week prose retrieved.',

            'response' => [
                'artifact' => '$context.artifact',
            ],
        ],

        'terminal_missing_week' => [

            'type' => 'terminal',

            'message' =>
                'A canonical Book week is required.',
        ],

        'terminal_no_week_prose_found' => [

            'type' => 'terminal',

            'message' =>
                'No prose found for that canonical Book week.',
        ],
    ],
];
