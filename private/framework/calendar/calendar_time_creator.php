<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';

function resolve_parent_day_for_calendar_time(
    PDO $pdo,
    string $parentDayEntityId
): array {
    $parentDayEntityId = trim($parentDayEntityId);

    if ($parentDayEntityId === '') {
        throw new InvalidArgumentException('parent_day_entity_id must be non-empty');
    }

    $stmt = $pdo->prepare("
        SELECT
            ce.id,
            ce.entity_id,
            ce.layer_id,
            ce.projection_entity_id
        FROM sxnzlfun_chrysalis.calendar_events ce
        WHERE ce.entity_id = :entity_id
          AND ce.layer_id = 'calendar_layer_day'
        LIMIT 1
    ");

    $stmt->execute([':entity_id' => $parentDayEntityId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'Invalid parent_day_entity_id: no matching calendar_layer_day row = ' . $parentDayEntityId
        );
    }

    if (empty($row['projection_entity_id'])) {
        throw new RuntimeException('Invalid parent day: missing projection_entity_id');
    }

    return $row;
}

function create_calendar_time(
    PDO $pdo,
    string $parentDayEntityId,
    int $timeIndex,
    ?string $timeLabel = null
): array {
    $timeLabel = trim((string) ($timeLabel ?? ''));

    if ($timeIndex < 1) {
        throw new InvalidArgumentException('time_index must be positive');
    }

    if ($timeLabel === '') {
        $timeLabel = 'Time ' . $timeIndex;
    }

    $parentDay = resolve_parent_day_for_calendar_time($pdo, $parentDayEntityId);

    return ensure_calendar_node(
        $pdo,
        trim((string) $parentDay['projection_entity_id']),
        'calendar_layer_time',
        (int) $parentDay['id'],
        $timeIndex,
        [
            'summary' => $timeLabel,
        ]
    );
}