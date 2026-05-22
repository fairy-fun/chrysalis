<?php

return [
    'workflow_id' => 'calendar_event_create',
    'tier' => 1,
    'intent' => 'Create a calendar event using canonical Book locality identity.',

    'entry_state' => 'await_projection_id',

    'states' => [

        'await_projection_id' => [
            'type' => 'input',
            'prompt' => 'Which book should this event belong to? You can answer with a book label such as Book 1.',
            'expected_input' => 'projection_id',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.projection_id',
                'cases' => [
                    '' => 'terminal_missing_projection_id',
                ],
                'default' => 'validate_projection',
            ],
        ],

        'validate_projection' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'projection',

                'sql' => '
                    SELECT
                        id,
                        entity_id,
                        projection_type_id,
                        projection_code
                    FROM calendar_projections
                    WHERE projection_type_id = :required_projection_type_id
                      AND (
                          (
                              :projection_id_numeric_probe REGEXP "^[0-9]+$"
                              AND id = CAST(:projection_id_numeric_value AS UNSIGNED)
                          )
                          OR entity_id = :projection_id_entity_id
                          OR projection_code = :projection_id_projection_code
                          OR LOWER(REPLACE(REPLACE(projection_code, "_", ""), "-", ""))
                             = LOWER(REPLACE(REPLACE(REPLACE(:projection_id_normalized_code, " ", ""), "_", ""), "-", ""))
                          OR CONCAT("book", CAST(id AS CHAR))
                             = LOWER(REPLACE(REPLACE(REPLACE(:projection_id_book_label, " ", ""), "_", ""), "-", ""))
                      )
                    ORDER BY id ASC
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id_numeric_probe' => '$input.projection_id',
                    'projection_id_numeric_value' => '$input.projection_id',
                    'projection_id_entity_id' => '$input.projection_id',
                    'projection_id_projection_code' => '$input.projection_id',
                    'projection_id_normalized_code' => '$input.projection_id',
                    'projection_id_book_label' => '$input.projection_id',
                    'required_projection_type_id' => '$context.required_projection_type_id',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'route_projection_ontology',
                'failure_state' => 'terminal_projection_not_found',
            ],
        ],

        'route_projection_ontology' => [
            'type' => 'action',

            'assert' => [
                'left' => '$context.projection.projection_type_id',
                'operator' => 'equals',
                'right' => '$context.required_projection_type_id',
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_week_index',
                'failure_state' => 'terminal_unsupported_projection_type',
            ],
        ],

        'await_week_index' => [
            'type' => 'input',
            'prompt' => 'Which week should this event belong to? You can answer with a number or a label such as Week 1.',
            'expected_input' => 'week_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.week_index',
                'cases' => [
                    '' => 'terminal_missing_week_index',
                ],
                'default' => 'validate_book_week',
            ],
        ],

        'validate_book_week' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'book_week',

                'sql' => '
                    SELECT
                        id,
                        entity_id,
                        projection_id,
                        week_index
                    FROM calendar_book_weeks
                    WHERE projection_id = :projection_id
                      AND week_index = CAST(
                          REPLACE(
                              REPLACE(
                                  REPLACE(
                                      LOWER(REPLACE(:week_index_input, "week", "")),
                                      " ",
                                      ""
                                  ),
                                  "_",
                                  ""
                              ),
                              "-",
                              ""
                          ) AS UNSIGNED
                      )
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.projection.id',
                    'week_index_input' => '$input.week_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_day_index',
                'failure_state' => 'terminal_book_week_not_found',
            ],
        ],

        'await_day_index' => [
            'type' => 'input',
            'prompt' => 'Which day should this event belong to? You can answer with a day name, a number, or a label such as Day 2.',
            'expected_input' => 'day_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.day_index',
                'cases' => [
                    '' => 'terminal_missing_day_index',
                ],
                'default' => 'validate_book_day',
            ],
        ],

        'validate_book_day' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'book_day',

                'sql' => '
                    SELECT
                        id,
                        entity_id,
                        projection_id,
                        week_id,
                        day_index,
                        day_of_week_id
                    FROM calendar_book_days
                    WHERE projection_id = :projection_id
                      AND week_id = :week_id
                      AND day_index = CASE
                          WHEN LOWER(TRIM(:day_name_sunday)) = "sunday" THEN 1
                          WHEN LOWER(TRIM(:day_name_monday)) = "monday" THEN 2
                          WHEN LOWER(TRIM(:day_name_tuesday)) = "tuesday" THEN 3
                          WHEN LOWER(TRIM(:day_name_wednesday)) = "wednesday" THEN 4
                          WHEN LOWER(TRIM(:day_name_thursday)) = "thursday" THEN 5
                          WHEN LOWER(TRIM(:day_name_friday)) = "friday" THEN 6
                          WHEN LOWER(TRIM(:day_name_saturday)) = "saturday" THEN 7
                          ELSE CAST(
                              REPLACE(
                                  REPLACE(
                                      REPLACE(
                                          LOWER(REPLACE(:day_index_input, "day", "")),
                                          " ",
                                          ""
                                      ),
                                      "_",
                                      ""
                                  ),
                                  "-",
                                  ""
                              ) AS UNSIGNED
                          )
                      END
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.projection.id',
                    'week_id' => '$context.book_week.id',
                    'day_name_sunday' => '$input.day_index',
                    'day_name_monday' => '$input.day_index',
                    'day_name_tuesday' => '$input.day_index',
                    'day_name_wednesday' => '$input.day_index',
                    'day_name_thursday' => '$input.day_index',
                    'day_name_friday' => '$input.day_index',
                    'day_name_saturday' => '$input.day_index',
                    'day_index_input' => '$input.day_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_time_index',
                'failure_state' => 'terminal_book_day_not_found',
            ],
        ],

        'await_time_index' => [
            'type' => 'input',
            'prompt' => 'Which time slot should this event belong to? You can answer with a number or a label such as Time 1.',
            'expected_input' => 'time_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.time_index',
                'cases' => [
                    '' => 'terminal_missing_time_index',
                ],
                'default' => 'validate_book_time',
            ],
        ],

        'validate_book_time' => [
            'type' => 'action',

            'action' => [
                'driver' => 'db',
                'operation' => 'select_one',
                'store' => 'book_time',

                'sql' => '
                    SELECT
                        id,
                        entity_id,
                        projection_id,
                        day_id,
                        time_index
                    FROM calendar_book_times
                    WHERE projection_id = :projection_id
                      AND day_id = :day_id
                      AND time_index = CAST(
                          REPLACE(
                              REPLACE(
                                  REPLACE(
                                      LOWER(REPLACE(:time_index_input, "time", "")),
                                      " ",
                                      ""
                                  ),
                                  "_",
                                  ""
                              ),
                              "-",
                              ""
                          ) AS UNSIGNED
                      )
                    LIMIT 1
                ',

                'bindings' => [
                    'projection_id' => '$context.projection.id',
                    'day_id' => '$context.book_day.id',
                    'time_index_input' => '$input.time_index',
                ],
            ],

            'success_if' => 'row_exists',

            'transition' => [
                'driver' => 'boolean',
                'next' => 'await_summary',
                'failure_state' => 'terminal_book_time_not_found',
            ],
        ],

        'await_summary' => [
            'type' => 'input',
            'prompt' => 'Enter a summary for the event.',
            'expected_input' => 'summary',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.summary',
                'cases' => [
                    '' => 'terminal_missing_summary',
                ],
                'default' => 'await_optional_event_index',
            ],
        ],

        'await_optional_event_index' => [
            'type' => 'input',
            'prompt' => 'What event index should this use? Leave blank to use the next available event index.',
            'expected_input' => 'event_index',

            'transition' => [
                'driver' => 'match',
                'value' => '$input.event_index',
                'cases' => [
                    '' => 'create_book_calendar_event',
                ],
                'default' => 'create_book_calendar_event',
            ],
        ],

        'create_book_calendar_event' => [
            'type' => 'action',

            'action' => [
                'driver' => 'calendar',
                'operation' => 'create_book_event',

                'payload' => [
                    'projection_id' => '$context.projection.id',
                    'book_time_id' => '$context.book_time.id',
                    'event_index' => '$input.event_index',
                    'summary' => '$input.summary',
                ],
            ],

            'transition' => [
                'driver' => 'boolean',
                'next' => 'terminal_event_created',
                'failure_state' => 'terminal_event_creation_failed',
            ],
        ],

        'terminal_event_created' => [
            'type' => 'terminal',
            'message' => 'Calendar event created successfully.',
            'handoff_packet' => '$context.handoff_packet',
        ],

        'terminal_event_creation_failed' => [
            'type' => 'terminal',
            'message' => 'Failed to create calendar event.',
        ],

        'terminal_missing_projection_id' => [
            'type' => 'terminal',
            'message' => 'A book projection is required.',
        ],

        'terminal_projection_not_found' => [
            'type' => 'terminal',
            'message' => 'Book projection not found.',
        ],

        'terminal_unsupported_projection_type' => [
            'type' => 'terminal',
            'message' => 'This workflow currently supports Book projections only.',
        ],

        'terminal_missing_week_index' => [
            'type' => 'terminal',
            'message' => 'A week is required.',
        ],

        'terminal_book_week_not_found' => [
            'type' => 'terminal',
            'message' => 'Book week does not exist in this projection.',
        ],

        'terminal_missing_day_index' => [
            'type' => 'terminal',
            'message' => 'A day is required.',
        ],

        'terminal_book_day_not_found' => [
            'type' => 'terminal',
            'message' => 'Book day does not exist in this week.',
        ],

        'terminal_missing_time_index' => [
            'type' => 'terminal',
            'message' => 'A time slot is required.',
        ],

        'terminal_book_time_not_found' => [
            'type' => 'terminal',
            'message' => 'Book time does not exist for this day.',
        ],

        'terminal_missing_summary' => [
            'type' => 'terminal',
            'message' => 'A summary is required.',
        ],
    ],

    'initial_context' => [
        'required_projection_type_id' => 'projection_type_book',
    ],
];