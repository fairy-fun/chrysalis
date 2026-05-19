<?php

return [

    'workflow_id' => 'calendar_day_display_prose',

    'tier' => 2,

    'intent' => 'Display all prose for a calendar day',

    'entry_state' => 'await_real_date_id',

    'states' => [

        'await_real_date_id' => [

            'type' => 'input',

            'prompt' =>
                'What is the real_date_start_id?',

            'expected_input' => 'real_date_start_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.real_date_start_id',

                'cases' => [
                    '' => 'terminal_missing_day',
                ],

                'default' => 'load_day_prose',
            ],
        ],

        'load_day_prose' => [

            'type' => 'action',

            'action' => [

                'driver' => 'calendar',

                'operation' => 'display_day_prose',

                'payload' => [
                    'real_date_start_id'
                    => '$input.real_date_start_id',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_day_prose',
                'failure_state' => 'terminal_no_prose_found',
            ],
        ],

        'terminal_day_prose' => [

            'type' => 'terminal',

            'message' =>
                'Calendar day prose retrieved.',

            'response' => [
                'artifact' => '$context.artifact',
            ],
        ],

        'terminal_missing_day' => [

            'type' => 'terminal',

            'message' =>
                'A real_date_start_id is required.',
        ],

        'terminal_no_prose_found' => [

            'type' => 'terminal',

            'message' =>
                'No prose found for that calendar day.',
        ],
    ],
];