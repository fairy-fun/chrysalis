<?php

return [

    'workflow_id' => 'calendar_week_day_display_prose',

    'tier' => 1,

    'intent' => 'Show the existing prose for a canonical Book week day',

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

                'default' => 'await_day_input',
            ],
        ],

        'await_day_input' => [

            'type' => 'input',

            'prompt' =>
                'Which Book day should be displayed?',

            'expected_input' => 'day',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.day',

                'cases' => [
                    '' => 'terminal_missing_day',
                ],

                'default' => 'load_week_day_prose',
            ],
        ],

        'load_week_day_prose' => [

            'type' => 'action',

            'action' => [

                'driver' => 'calendar',

                'operation' => 'display_week_day_prose',

                'payload' => [
                    'week' => '$input.week',
                    'day' => '$input.day',
                    'projection_id' => '$context.projection.id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_week_day_prose',
                'failure_state' => 'terminal_no_week_day_prose_found',
            ],
        ],

        'terminal_week_day_prose' => [

            'type' => 'terminal',

            'message' =>
                'Canonical Book week day prose retrieved.',

            'response' => [
                'artifact' => '$context.artifact',
            ],
        ],

        'terminal_missing_week' => [

            'type' => 'terminal',

            'message' =>
                'A canonical Book week is required.',
        ],

        'terminal_missing_day' => [

            'type' => 'terminal',

            'message' =>
                'A canonical Book day is required.',
        ],

        'terminal_no_week_day_prose_found' => [

            'type' => 'terminal',

            'message' =>
                'No prose found for that canonical Book week day.',
        ],
    ],
];
