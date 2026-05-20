<?php

return [
    'workflow_id' => 'calendar_book_event_create',

    'delegates_to' => 'calendar_event_create',

    'initial_context' => [
        'required_projection_type_id' => 'projection_type_book',
    ],
];
