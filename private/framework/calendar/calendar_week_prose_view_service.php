<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_week_prose_label_formatter.php';
require_once __DIR__ . '/calendar_week_prose_item_hydrator.php';
require_once __DIR__ . '/calendar_week_prose_query.php';

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

    $week = query_calendar_week(
        $pdo,
        $projectionId,
        $weekIndex
    );

    if ($week === null) {
        return null;
    }

    $days = query_calendar_week_days(
        $pdo,
        $projectionId,
        (int)$week['id']
    );

    $weekTree = [
        'prose_mode' => $proseMode,
        'week' => $week,
        'days' => [],
    ];

    if (!$days) {
        return $weekTree;
    }

    $exportPredicate = $proseMode === 'export'
        ? ' AND is_export_target = 1'
        : '';

    $exportPredicateP1 = $proseMode === 'export'
        ? ' AND p1.is_export_target = 1'
        : '';

    $exportPredicatePOrder = $proseMode === 'export'
        ? ' AND p_order.is_export_target = 1'
        : '';

    foreach ($days as $day) {

        $times = query_calendar_day_times(
            $pdo,
            $projectionId,
            (int)$day['id']
        );

        $hydratedTimes = [];

        foreach ($times as $time) {

            $events = query_calendar_time_events(
                $pdo,
                $projectionId,
                (int)$time['id'],
                $proseMode
            );

            $hydratedTimes[] = [
                'id' => (int)$time['id'],
                'entity_id' => $time['entity_id'],
                'time_index' => (int)$time['time_index'],
                'display_label' => $time['display_label'],
                'summary' => $time['summary'],
                'notes' => $time['notes'],
                'events' => array_map(
                    static function (array $event): array {

                        $layerId = $event['subevent_index'] !== null
                            ? 'calendar_layer_subevent'
                            : 'calendar_layer_event';

                        $summary = trim((string)($event['summary'] ?? ''));

                        $proseBody = trim((string)($event['prose_body'] ?? ''));

                        $notes = trim((string)($event['notes'] ?? ''));

                        return hydrate_calendar_week_prose_item(
                            $event,
                            $layerId,
                            $summary,
                            $proseBody,
                            $notes
                        );
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
