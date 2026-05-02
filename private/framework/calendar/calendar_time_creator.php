<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';

function create_calendar_time(
    PDO $pdo,
    string $projectionEntityId,
    int $parentWeekSequenceIndex,
    int $parentDaySequenceIndex,
    int $timeIndex,
    ?string $timeLabel = null
): array {
    $projectionEntityId = trim($projectionEntityId);
    $timeLabel = trim((string) ($timeLabel ?? ''));

    if ($projectionEntityId === '') {
        throw new InvalidArgumentException('projection_entity_id must be non-empty');
    }

    if ($parentWeekSequenceIndex < 1) {
        throw new InvalidArgumentException('parent_week_sequence_index must be positive');
    }

    if ($parentDaySequenceIndex < 1) {
        throw new InvalidArgumentException('parent_day_sequence_index must be positive');
    }

    if ($timeIndex < 1) {
        throw new InvalidArgumentException('time_index must be positive');
    }

    if ($timeLabel === '') {
        $timeLabel = 'Time ' . $timeIndex;
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
        (int) $parentWeek['id'],
        $parentDaySequenceIndex
    );

    return ensure_calendar_node(
        $pdo,
        $projectionEntityId,
        'calendar_layer_time',
        (int) $parentDay['id'],
        $timeIndex,
        [
            'summary' => $timeLabel,
        ]
    );
}