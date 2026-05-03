<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';

/*
 * STRUCTURAL CREATOR ONLY
 *
 * This function constructs calendar nodes using layer-based hierarchy.
 * It does not perform semantic validation.
 *
 * Entity types are assigned and validated by calendar_node_ensurer.
 */

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

function create_calendar_day(
    PDO $pdo,
    string $projectionEntityId,
    int $parentWeekSequenceIndex,
    int $dayIndex,
    string $dayLabel,
    string $realDateId
): array {
    $projectionEntityId = trim($projectionEntityId);
    $dayLabel = trim($dayLabel);
    $realDateId = trim($realDateId);

    if ($projectionEntityId === '') {
        throw new InvalidArgumentException('projection_entity_id must be non-empty');
    }

    if ($parentWeekSequenceIndex < 1) {
        throw new InvalidArgumentException('parent_week_sequence_index must be positive');
    }

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

    $parentWeek = require_calendar_node(
        $pdo,
        $projectionEntityId,
        'calendar_layer_week',
        null,
        $parentWeekSequenceIndex
    );

    return ensure_calendar_node(
        $pdo,
        $projectionEntityId,
        'calendar_layer_day',
        (int) $parentWeek['event_id'], // ✅ FIXED
        $dayIndex,
        [
            'summary' => $dayLabel,
            'real_date_start_id' => $realDateId,
        ]
    );
}