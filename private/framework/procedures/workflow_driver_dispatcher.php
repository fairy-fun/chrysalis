<?php
declare(strict_types=1);

require_once __DIR__ . '/workflow_calendar_input_normalizer.php';

function fw_execute_workflow_driver_operation(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $driver = $action['driver'] ?? null;
    $operation = $action['operation'] ?? null;

    if (!is_string($driver) || $driver === '') {
        throw new RuntimeException(
            'Missing workflow action driver.'
        );
    }

    if (!is_string($operation) || $operation === '') {
        throw new RuntimeException(
            'Missing workflow action operation.'
        );
    }

    $map = [

        'db' => [

            'select_one' => 'fw_execute_workflow_db_select_one',

        ],

        'prose' => [

            'create_draft' => 'fw_execute_workflow_prose_create_draft',
            'segment_subevents' => 'fw_execute_workflow_prose_segment_subevents',
        ],

        'calendar' => [
            'normalize_book_event_input' => 'fw_execute_workflow_calendar_normalize_book_event_input',
            'create_book_event' => 'fw_execute_workflow_calendar_create_book_event',
            'create_book_week' => 'fw_execute_workflow_calendar_book_week_create',
            'create_book_day' => 'fw_execute_workflow_calendar_book_day_create',
            'create_book_time' => 'fw_execute_workflow_calendar_book_time_create',
            'process_attached_prose' => 'fw_execute_workflow_calendar_event_process_attached_prose',
            'derive_beat_title' => 'fw_execute_workflow_calendar_event_derive_beat_title',
            'display_day_prose' => 'fw_execute_workflow_calendar_display_day_prose',
            'display_week_prose' => 'fw_execute_workflow_calendar_display_week_prose',
            'display_week_day_prose' => 'fw_execute_workflow_calendar_display_week_day_prose',
        ],

    ];

    $handler = $map[$driver][$operation] ?? null;

    if (!is_string($handler) || !function_exists($handler)) {

        throw new RuntimeException(
            "Unsupported workflow driver operation: {$driver}.{$operation}"
        );
    }

    return $handler(
        $pdo,
        $action,
        $input,
        $context
    );
}
