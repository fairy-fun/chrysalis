<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_week_prose_label_formatter.php';

function normalize_calendar_week_prose_mode(
    ?string $proseMode
): string {

    $normalised = strtolower(
        trim((string)($proseMode ?? ''))
    );

    if ($normalised === '') {
        return 'export';
    }

    if ($normalised === 'all') {
        return 'published';
    }

    if (!in_array($normalised, ['export', 'published'], true)) {
        throw new InvalidArgumentException(
            'Unsupported prose retrieval mode: ' . $normalised
        );
    }

    return $normalised;
}

function resolve_calendar_week_prose_view(
    PDO $pdo,
    int $projectionId,
    int $weekIndex,
    string $proseMode = 'export'
): ?array {

    $proseMode = normalize_calendar_week_prose_mode(
        $proseMode
    );

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
        'prose_mode' => $proseMode,
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

    $exportPredicate = $proseMode === 'export'
        ? ' AND is_export_target = 1'
        : '';

    $exportPredicateP1 = $proseMode === 'export'
        ? ' AND p1.is_export_target = 1'
        : '';

    $exportPredicatePOrder = $proseMode === 'export'
        ? ' AND p_order.is_export_target = 1'
        : '';

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
            pp.projection_order AS prose_projection_order,
            pp.is_export_target,

            pd.prose_body

        FROM calendar_events e

        LEFT JOIN (
            SELECT
                p1.target_entity_id,
                p1.id,
                p1.published_prose_draft_id,
                p1.projection_order,
                p1.is_export_target
            FROM prose_projections p1
            INNER JOIN (
                SELECT
                    p_order.target_entity_id,
                    MIN(p_order.id) AS resolved_projection_id
                FROM prose_projections p_order
                INNER JOIN (
                    SELECT
                        target_entity_id,
                        MIN(projection_order) AS resolved_projection_order
                    FROM prose_projections
                    WHERE published_prose_draft_id IS NOT NULL
                      AND projection_order IS NOT NULL
                      AND role_id = 'prose_projection_role_primary'
                      AND projection_type_id = 'projection_type_timeline_view'
                      {$exportPredicate}
                    GROUP BY target_entity_id
                ) resolved_order
                    ON resolved_order.target_entity_id = p_order.target_entity_id
                   AND resolved_order.resolved_projection_order = p_order.projection_order
                WHERE p_order.published_prose_draft_id IS NOT NULL
                  AND p_order.projection_order IS NOT NULL
                  AND p_order.role_id = 'prose_projection_role_primary'
                  AND p_order.projection_type_id = 'projection_type_timeline_view'
                  {$exportPredicatePOrder}
                GROUP BY p_order.target_entity_id
            ) resolved
                ON resolved.resolved_projection_id = p1.id
            WHERE p1.published_prose_draft_id IS NOT NULL
              AND p1.projection_order IS NOT NULL
              AND p1.role_id = 'prose_projection_role_primary'
              AND p1.projection_type_id = 'projection_type_timeline_view'
              {$exportPredicateP1}
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

                            'prose_projection_order'
                                => $event['prose_projection_order'] !== null
                                    ? (int)$event['prose_projection_order']
                                    : null,

                            'is_export_target'
                                => $event['is_export_target'] !== null
                                    ? (int)$event['is_export_target']
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

function render_calendar_week_prose_dot_notation(
    array $renderTree,
    array $day,
    array $time,
    array $event
): string {

    $parts = [
        (int)($renderTree['week']['week_index'] ?? 0),
        (int)($day['day_index'] ?? 0),
        (int)($time['time_index'] ?? 0),
    ];

    if ($event['event_index'] !== null) {
        $parts[] = (int)$event['event_index'];
    }

    if ($event['subevent_index'] !== null) {
        $parts[] = (int)$event['subevent_index'];
    }

    if (
        $event['subevent_index'] !== null
        && $event['sequence_index'] !== null
    ) {
        $parts[] = (int)$event['sequence_index'];
    }

    return implode('.', $parts);
}

function render_calendar_week_prose_item_label(
    array $renderTree,
    array $day,
    array $time,
    array $event
): string {

    $dotNotation = render_calendar_week_prose_dot_notation(
        $renderTree,
        $day,
        $time,
        $event
    );

    $displayReference = trim((string)($event['reference_label'] ?? ''));

    if ($displayReference === '') {
        $displayReference = 'Event ' . (int)($event['event_index'] ?? 0);
    }

    $label = format_calendar_week_prose_label(
        $renderTree,
        $day,
        $time,
        $event
    );

    $summary = trim((string)($event['summary'] ?? ''));

    if ($summary !== '') {
        $label .= ': ' . $summary;
    }

    return $label;
}

function render_calendar_week_prose_tail(
    string $proseBody,
    int $length = 200
): string {

    $normalised = preg_replace('/\s+/u', ' ', trim($proseBody));

    if (!is_string($normalised) || $normalised === '') {
        return '';
    }

    if (mb_strlen($normalised) <= $length) {
        return $normalised;
    }

    return '…' . mb_substr(
        $normalised,
        -1 * $length
    );
}

function render_calendar_week_prose_artifact(
    array $weekTree,
    ?int $dayIndex = null
): array {
    $tailPreviewLines = [];
    $proseItems = [];
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

                $dotNotation = render_calendar_week_prose_dot_notation(
                    $renderTree,
                    $day,
                    $time,
                    $event
                );

                $label = render_calendar_week_prose_item_label(
                    $renderTree,
                    $day,
                    $time,
                    $event
                );

                $tail = render_calendar_week_prose_tail(
                    $proseBody,
                    200
                );

                $proseItems[] = [
                    'dot_notation' => $dotNotation,
                    'dot_notation_policy'
                        => 'Backend-derived render identity only. Do not use for retrieval authority.',
                    'canonical_label' => $label,
                    'week_index' => (int)($renderTree['week']['week_index'] ?? 0),
                    'day_index' => (int)($day['day_index'] ?? 0),
                    'time_index' => (int)($time['time_index'] ?? 0),
                    'time_label' => (string)($time['display_label'] ?? ('Time ' . ($time['time_index'] ?? ''))),
                    'event_index' => $event['event_index'],
                    'subevent_index' => $event['subevent_index'],
                    'sequence_index' => $event['sequence_index'],
                    'event_entity_id' => (string)($event['entity_id'] ?? ''),
                    'event_summary' => trim((string)($event['summary'] ?? '')),
                    'prose_projection_id' => $event['prose_projection_id'],
                    'published_prose_draft_id' => $event['published_prose_draft_id'],
                    'prose_projection_order' => $event['prose_projection_order'],
                    'is_export_target' => $event['is_export_target'],
                    'prose_tail_200' => $tail,
                ];

                $tailPreviewLines[] = $label . "\n" . $tail;
            }
        }
    }

    return [
        'type' => $dayIndex !== null
            ? 'calendar_week_day_prose'
            : 'calendar_week_prose',
        'render_identity_policy'
            => 'Use dot_notation and canonical_label for display. Do not use dot_notation as retrieval authority. Parent events render as week.day.time.event; subevent positions are rendered only when subevent indexes exist.',
        'preview_policy'
            => 'Lightweight prose preview only: each prose item includes the last 200 characters of its published prose body. Full prose bodies are intentionally omitted from this workflow artifact to avoid oversized chat responses.',
        'prose_mode' => normalize_calendar_week_prose_mode(
            (string)($renderTree['prose_mode'] ?? 'export')
        ),
        'week_index' => (int)($renderTree['week']['week_index'] ?? 0),
        'day_index' => $dayIndex,
        'event_count' => $eventCount,
        'prose_item_count' => $proseCount,
        'prose_items' => $proseItems,
        'assembled_prose_preview' => implode("\n\n", $tailPreviewLines),
    ];
}

