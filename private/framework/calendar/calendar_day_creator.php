<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';

function validate_calendar_day_real_date_start_id_exists(PDO $pdo, string $realDateStartId): void
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM sxnzlfun_chrysalis.dates
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $realDateStartId]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException(
            'Invalid real_date_start_id: no matching dates.id = ' . $realDateStartId
        );
    }
}

function resolve_parent_week_for_calendar_day(
    PDO $pdo,
    string $parentWeekEntityId
): array {
    $parentWeekEntityId = trim($parentWeekEntityId);

    if ($parentWeekEntityId === '') {
        throw new InvalidArgumentException('parent_week_entity_id must be non-empty');
    }

    $stmt = $pdo->prepare("
        SELECT
            ce.id,
            ce.entity_id,
            ce.layer_id,
            ce.projection_entity_id
        FROM sxnzlfun_chrysalis.calendar_events ce
        WHERE ce.entity_id = :entity_id
          AND ce.layer_id = 'calendar_layer_week'
        LIMIT 1
    ");

    $stmt->execute([':entity_id' => $parentWeekEntityId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'Invalid parent_week_entity_id: no matching calendar_layer_week row = ' . $parentWeekEntityId
        );
    }

    if (empty($row['projection_entity_id'])) {
        throw new RuntimeException('Invalid parent week: missing projection_entity_id');
    }

    return $row;
}

function create_calendar_day(
    PDO $pdo,
    string $parentWeekEntityId,
    int $dayIndex,
    string $dayLabel,
    string $realDateId
): array {
    $dayLabel = trim($dayLabel);
    $realDateId = trim($realDateId);

    if ($dayIndex < 1) {
        throw new InvalidArgumentException('day_index must be positive');
    }

    if ($dayLabel === '') {
        throw new InvalidArgumentException('day_label must be non-empty');
    }

    if ($realDateId === '') {
        throw new InvalidArgumentException('real_date_id must be non-empty');
    }

    validate_calendar_day_real_date_start_id_exists($pdo, $realDateId);

    $parentWeek = resolve_parent_week_for_calendar_day($pdo, $parentWeekEntityId);

    return ensure_calendar_node(
        $pdo,
        trim((string) $parentWeek['projection_entity_id']),
        'calendar_layer_day',
        (int) $parentWeek['id'],
        $dayIndex,
        [
            'summary' => $dayLabel,
            'real_date_start_id' => $realDateId,
        ]
    );
}