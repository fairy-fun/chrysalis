<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__ . '/../calendar/calendar_book_chronology_ensurer.php';
require_once __DIR__ . '/../calendar/calendar_book_event_ensurer.php';

function fw_execute_workflow_calendar_create_book_event(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $projectionId = (int)($payload['projection_id'] ?? 0);
    $weekIndex = (int)($payload['week_index'] ?? 0);
    $dayIndex = (int)($payload['day_index'] ?? 0);
    $timeIndex = (int)($payload['time_index'] ?? 0);

    $eventIndex = null;

    if (
        array_key_exists('event_index', $payload)
        && $payload['event_index'] !== null
        && $payload['event_index'] !== ''
    ) {
        $eventIndex = (int)$payload['event_index'];
    }

    $summary = trim((string)($payload['summary'] ?? ''));

    if ($projectionId < 1) {
        throw new RuntimeException(
            'create_book_event requires projection_id'
        );
    }

    if ($weekIndex < 1) {
        throw new RuntimeException(
            'create_book_event requires week_index'
        );
    }

    if ($dayIndex < 1) {
        throw new RuntimeException(
            'create_book_event requires day_index'
        );
    }

    if ($timeIndex < 1) {
        throw new RuntimeException(
            'create_book_event requires time_index'
        );
    }

    if ($eventIndex !== null && $eventIndex < 1) {
        throw new RuntimeException(
            'event_index must be positive'
        );
    }

    if ($summary === '') {
        throw new RuntimeException(
            'create_book_event requires summary'
        );
    }

    $bookTimeId = resolve_calendar_book_time_id(
        $pdo,
        $projectionId,
        $weekIndex,
        $dayIndex,
        $timeIndex
    );

    $event = ensure_calendar_book_event(
        $pdo,
        $bookTimeId,
        $eventIndex,
        [
            'summary' => $summary,
        ]
    );

    return [
        'success' => true,

        'context' => array_merge(
            $context,
            [
                'calendar_event' => $event,

                'calendar_book_event_create' => [
                    'projection_id' => $projectionId,
                    'book_time_id' => $bookTimeId,
                    'week_index' => $weekIndex,
                    'day_index' => $dayIndex,
                    'time_index' => $timeIndex,
                    'event_index' => $event['event_index'] ?? $eventIndex,
                    'entity_id' => $event['entity_id'] ?? null,
                    'id' => $event['id'] ?? null,
                ],
            ]
        ),
    ];
}