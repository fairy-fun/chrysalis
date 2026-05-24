<?php

declare(strict_types=1);

function resolve_calendar_week_prose_view(
    PDO $pdo,
    int $projectionId,
    int $weekIndex
): ?array {

    if ($projectionId <= 0) {
        throw new InvalidArgumentException(
            'projection_id must be a positive integer'
        );
    }

    if ($weekIndex <= 0) {
        throw new InvalidArgumentException(
            'week_index must be a positive integer'
        );
    }

    $weekStmt = $pdo->prepare("
        SELECT
            w.id,
            w.entity_id,
            w.projection_id,
            w.week_index,
            w.summary,
            w.notes
        FROM calendar_book_weeks w
        WHERE w.projection_id = :projection_id
          AND w.week_index = :week_index
        LIMIT 1
    ");

    $weekStmt->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
    ]);

    $week = $weekStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($week)) {
        return null;
    }

    $daysStmt = $pdo->prepare("
        SELECT
            d.id,
            d.entity_id,
            d.projection_id,
            d.week_id,
            d.day_index,
            d.summary,
            d.notes
        FROM calendar_book_days d
        WHERE d.projection_id = :projection_id
          AND d.week_id = :week_id
        ORDER BY d.day_index ASC
    ");

    $daysStmt->execute([
        ':projection_id' => $projectionId,
        ':week_id' => (int)$week['id'],
    ]);

    $days = $daysStmt->fetchAll(PDO::FETCH_ASSOC);

    $weekTree = [
        'week' => $week,
        'days' => [],
    ];

    if (!$days) {
        return $weekTree;
    }

    $timesStmt = $pdo->prepare("
        SELECT
            t.id,
            t.entity_id,
            t.projection_id,
            t.day_id,
            t.time_index,
            t.summary,
            t.notes,
            t.time_label_id,

            cv.id AS matched_time_label_id,
            cv.label AS classval_label,

            COALESCE(
                NULLIF(TRIM(cv.label), ''),
                NULLIF(TRIM(t.summary), ''),
                NULLIF(TRIM(t.notes), ''),
                CONCAT('Time ', t.time_index)
            ) AS display_label

        FROM calendar_book_times t

        LEFT JOIN calendar_time_label_classvals cv
            ON TRIM(cv.id) = TRIM(t.time_label_id)

        WHERE t.projection_id = :projection_id
          AND t.day_id = :day_id

        ORDER BY t.time_index ASC
    ");

    $eventsStmt = $pdo->prepare("
        SELECT
            e.id,
            e.entity_id,
            e.projection_id,
            e.book_time_id,
            e.event_index,
            e.subevent_index,
            e.sequence_index,
            e.summary,
            e.notes,

            pp.id AS prose_projection_id,
            pp.published_prose_draft_id,

            pd.prose_body

        FROM calendar_events e

        LEFT JOIN (
            SELECT
                p1.target_entity_id,
                p1.id,
                p1.published_prose_draft_id
            FROM prose_projections p1
            INNER JOIN (
                SELECT
                    target_entity_id,
                    MIN(id) AS resolved_projection_id
                FROM prose_projections
                WHERE published_prose_draft_id IS NOT NULL
                  AND role_id = 'prose_projection_role_primary'
                  AND projection_type_id = 'projection_type_timeline_view'
                  AND is_export_target = 1
                GROUP BY target_entity_id
            ) resolved
                ON resolved.resolved_projection_id = p1.id
            WHERE p1.published_prose_draft_id IS NOT NULL
              AND p1.role_id = 'prose_projection_role_primary'
              AND p1.projection_type_id = 'projection_type_timeline_view'
              AND p1.is_export_target = 1
        ) pp
            ON pp.target_entity_id = e.entity_id

        LEFT JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id

        WHERE e.projection_id = :projection_id
          AND e.book_time_id = :book_time_id

        ORDER BY
            e.event_index ASC,
            e.subevent_index ASC,
            e.sequence_index ASC,
            e.entity_id ASC
    ");

    foreach ($days as $day) {

        $timesStmt->execute([
            ':projection_id' => $projectionId,
            ':day_id' => (int)$day['id'],
        ]);

        $times = $timesStmt->fetchAll(PDO::FETCH_ASSOC);

        $hydratedTimes = [];

        foreach ($times as $time) {

            $eventsStmt->execute([
                ':projection_id' => $projectionId,
                ':book_time_id' => (int)$time['id'],
            ]);

            $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

            $hydratedTimes[] = [
                'id' => (int)$time['id'],
                'entity_id' => $time['entity_id'],
                'time_index' => (int)$time['time_index'],
                'display_label' => $time['display_label'],
                'summary' => $time['summary'],
                'notes' => $time['notes'],
                'events' => array_map(
                    static function (array $event): array {

                        return [
                            'id' => (int)$event['id'],
                            'entity_id' => $event['entity_id'],

                            'projection_id'
                                => (int)$event['projection_id'],

                            'book_time_id'
                                => (int)$event['book_time_id'],

                            'event_index'
                                => $event['event_index'] !== null
                                    ? (int)$event['event_index']
                                    : null,

                            'subevent_index'
                                => $event['subevent_index'] !== null
                                    ? (int)$event['subevent_index']
                                    : null,

                            'sequence_index'
                                => $event['sequence_index'] !== null
                                    ? (int)$event['sequence_index']
                                    : null,

                            'summary' => $event['summary'],
                            'notes' => $event['notes'],

                            'prose_projection_id'
                                => $event['prose_projection_id'] !== null
                                    ? (int)$event['prose_projection_id']
                                    : null,

                            'published_prose_draft_id'
                                => $event['published_prose_draft_id'] !== null
                                    ? (int)$event['published_prose_draft_id']
                                    : null,

                            'prose_body' => $event['prose_body'],
                        ];
                    },
                    $events
                ),
            ];
        }

        $weekTree['days'][] = [
            'id' => (int)$day['id'],
            'entity_id' => $day['entity_id'],
            'day_index' => (int)$day['day_index'],
            'summary' => $day['summary'],
            'notes' => $day['notes'],
            'times' => $hydratedTimes,
        ];
    }

    return $weekTree;
}

function filter_calendar_week_prose_view_to_day(
    array $weekTree,
    int $dayIndex
): array {

    $filtered = $weekTree;

    $filtered['days'] = array_values(
        array_filter(
            $weekTree['days'] ?? [],
            static function (array $day) use ($dayIndex): bool {
                return (int)($day['day_index'] ?? 0) === $dayIndex;
            }
        )
    );

    return $filtered;
}

function render_calendar_week_prose_artifact(
    array $weekTree,
    ?int $dayIndex = null
): array {
    $assembled = [];
    $eventCount = 0;
    $proseCount = 0;

    $renderTree = $dayIndex !== null
        ? filter_calendar_week_prose_view_to_day(
            $weekTree,
            $dayIndex
        )
        : $weekTree;

    foreach (($renderTree['days'] ?? []) as $day) {
        foreach (($day['times'] ?? []) as $time) {
            foreach (($time['events'] ?? []) as $event) {
                $eventCount++;

                $proseBody = trim((string)($event['prose_body'] ?? ''));

                if ($proseBody === '') {
                    continue;
                }

                $proseCount++;

                $heading = sprintf(
                    'Week %d, Day %d, %s, Event %d (%s)',
                    (int)($renderTree['week']['week_index'] ?? 0),
                    (int)($day['day_index'] ?? 0),
                    (string)($time['display_label'] ?? ('Time ' . ($time['time_index'] ?? ''))),
                    (int)($event['event_index'] ?? 0),
                    (string)($event['entity_id'] ?? '')
                );

                $summary = trim((string)($event['summary'] ?? ''));

                if ($summary !== '') {
                    $heading .= ': ' . $summary;
                }

                $assembled[] = $heading . "\n" . $proseBody;
            }
        }
    }

    return [
        'type' => $dayIndex !== null
            ? 'calendar_week_day_prose'
            : 'calendar_week_prose',
        'week_index' => (int)($renderTree['week']['week_index'] ?? 0),
        'day_index' => $dayIndex,
        'event_count' => $eventCount,
        'prose_item_count' => $proseCount,
        'tree' => $renderTree,
        'assembled_prose' => implode("\n\n", $assembled),
    ];
}
