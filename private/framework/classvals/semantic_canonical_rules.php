<?php

declare(strict_types=1);

return [
    [
        'name' => 'event_theme_fact',
        'canonical' => [
            'id' => 'fact_event_has_theme',
            'code' => 'event_has_theme',
        ],
        'deprecated' => [
            'ids' => [
                'fact_type_event_theme',
            ],
            'codes' => [
                'event_theme',
            ],
        ],
    ],
];