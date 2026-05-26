<?php

declare(strict_types=1);

function format_calendar_week_prose_label(
    array $renderTree,
    array $day,
    array $time,
    array $event
): string {

    $dotNotation = trim((string)($event['chronology_address'] ?? ''));

    $displayReference = trim(
        (string)($event['reference_label'] ?? '')
    );

    if ($displayReference === '') {
        $displayReference = 'Event ' . (int)($event['event_index'] ?? 0);
    }

    $timeLabel = (string)(
        $time['display_label']
        ?? ('Time ' . ($time['time_index'] ?? ''))
    );

    return sprintf(
        '%s — Week %d, Day %d, %s, %s (%s)',
        $dotNotation,
        (int)($renderTree['week']['week_index'] ?? 0),
        (int)($day['day_index'] ?? 0),
        $timeLabel,
        $displayReference,
        (string)($event['entity_id'] ?? '')
    );
}