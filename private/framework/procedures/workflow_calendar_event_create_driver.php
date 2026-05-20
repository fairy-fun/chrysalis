<?php

declare(strict_types=1);

require_once __DIR__ . '/../calendar/calendar_book_event_ensurer.php';

function fw_execute_workflow_calendar_create_book_event(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = $action['payload'] ?? [];

    $projectionId = (int) fw_workflow_value(
        $payload['projection_id'] ?? null,
        $input,
        $context
    );

    $weekIndex = (int) fw_workflow_value(
        $payload['week_index'] ?? null,
        $input,
        $context
    );

    $dayIndex = (int) fw_workflow_value(
        $payload['day_index'] ?? null,
        $input,
        $context
    );

    $timeIndex = (int) fw_workflow_value(
        $payload['time_index'] ?? null,
        $input,
        $context
    );

    $eventIndexRaw = fw_workflow_value(
        $payload['event_index'] ?? null,
        $input,
        $context
    );

    $summary = trim((string) fw_workflow_value(
        $payload['summary'] ?? null,
        $input,
        $context
    ));

    if ($projectionId < 1) {
        throw new RuntimeException(
            'workflow create_book_event requires projection_id'
        );
    }

    if ($weekIndex < 1) {
        throw new RuntimeException(
            'workflow create_book_event requires week_index'
        );
    }

    if ($dayIndex < 1) {
        throw new RuntimeException(
            'workflow create_book_event requires day_index'
        );
    }

    if ($timeIndex < 1) {
        throw new RuntimeException(
            'workflow create_book_event requires time_index'
        );
    }

    if ($summary === '') {
        throw new RuntimeException(
            'workflow create_book_event requires summary'
        );
    }

    $eventIndex = null;

    if (
        $eventIndexRaw !== null
        && $eventIndexRaw !== ''
    ) {
        $eventIndex = (int)$eventIndexRaw;

        if ($eventIndex < 1) {
            throw new RuntimeException(
                'event_index must be positive'
            );
        }
    }

    /*
     * Canonical Book chronology resolution.
     *
     * Tier 1 workflows create chronology-local Book events through:
     *
     *   calendar_book_weeks
     *       -> calendar_book_days
     *           -> calendar_book_times
     *               -> calendar_events
     *
     * NOT through recursive calendar_events ancestry.
     */

    $stmt = $pdo->prepare("
        SELECT cbt.*
        FROM sxnzlfun_chrysalis.calendar_book_times cbt

        INNER JOIN sxnzlfun_chrysalis.calendar_book_days cbd
            ON cbd.id = cbt.day_id

        INNER JOIN sxnzlfun_chrysalis.calendar_book_weeks cbw
            ON cbw.id = cbd.week_id

        WHERE cbw.projection_id = :projection_id
          AND cbd.projection_id = :projection_id
          AND cbt.projection_id = :projection_id

          AND cbw.week_index = :week_index
          AND cbd.day_index = :day_index
          AND cbt.time_index = :time_index

        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
        ':day_index' => $dayIndex,
        ':time_index' => $timeIndex,
    ]);

    $bookTime = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($bookTime)) {

        throw new RuntimeException(
            'Canonical calendar_book_time not found for chronology tuple'
        );
    }

    $bookTimeId = (int)($bookTime['id'] ?? 0);

    if ($bookTimeId < 1) {

        throw new RuntimeException(
            'Resolved calendar_book_time missing id'
        );
    }

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
            ]
        ),
    ];
}

/**
 * Minimal workflow value resolver bridge.
 *
 * Existing workflow system already uses '$input.foo'
 * and '$context.foo.bar' conventions.
 */
function fw_workflow_value(
    mixed $value,
    array $input,
    array $context
): mixed {

    if (!is_string($value)) {
        return $value;
    }

    if (str_starts_with($value, '$input.')) {

        $key = substr($value, 7);

        return $input[$key] ?? null;
    }

    if (str_starts_with($value, '$context.')) {

        $path = substr($value, 9);

        $segments = explode('.', $path);

        $current = $context;

        foreach ($segments as $segment) {

            if (!is_array($current)) {
                return null;
            }

            if (!array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    return $value;
}