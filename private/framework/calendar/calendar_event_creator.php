<?php

declare(strict_types=1);

/**
 * Calendar Event Creator (Structural Model)
 *
 * This file MUST NOT perform direct inserts.
 * All writes go through ensure_calendar_node().
 */

require_once __DIR__ . '/calendar_node_ensurer.php';

/**
 * Create or ensure a calendar event under a TIME node.
 */
function create_calendar_event(
    PDO $pdo,
    string $projectionEntityId,
    int $parentWeekSequenceIndex,
    int $parentDaySequenceIndex,
    int $parentTimeSequenceIndex,
    ?int $eventSequenceIndex = null,
    ?string $eventLabel = null
): array {
    $projectionEntityId = trim($projectionEntityId);
    $eventLabel = trim((string) ($eventLabel ?? ''));

    if ($projectionEntityId === '') {
        throw new InvalidArgumentException('projection_entity_id must be non-empty');
    }

    if ($parentWeekSequenceIndex < 1) {
        throw new InvalidArgumentException('parent_week_sequence_index must be positive');
    }

    if ($parentDaySequenceIndex < 1) {
        throw new InvalidArgumentException('parent_day_sequence_index must be positive');
    }

    if ($parentTimeSequenceIndex < 1) {
        throw new InvalidArgumentException('parent_time_sequence_index must be positive');
    }

    if ($eventSequenceIndex !== null && $eventSequenceIndex < 1) {
        throw new InvalidArgumentException('event_sequence_index must be positive when provided');
    }

    $parentWeek = require_calendar_node(
        $pdo,
        $projectionEntityId,
        'calendar_layer_week',
        null,
        $parentWeekSequenceIndex
    );

    $parentDay = require_calendar_node(
        $pdo,
        $projectionEntityId,
        'calendar_layer_day',
        (int) $parentWeek['event_id'],
        $parentDaySequenceIndex
    );

    $parentTime = require_calendar_node(
        $pdo,
        $projectionEntityId,
        'calendar_layer_time',
        (int) $parentDay['event_id'],
        $parentTimeSequenceIndex
    );

    if ($eventLabel === '') {
        $eventLabel = $eventSequenceIndex === null
            ? 'Event'
            : 'Event ' . $eventSequenceIndex;
    }

    return ensure_calendar_node(
        $pdo,
        $projectionEntityId,
        'calendar_layer_event',
        (int) $parentTime['event_id'],
        $eventSequenceIndex,
        [
            'summary' => $eventLabel,
        ]
    );
}