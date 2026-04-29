<?php

declare(strict_types=1);

require_once __DIR__ . '/../entities/entity_creator.php';
require_once __DIR__ . '/../procedures/calendar_event_id.php';

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

function create_calendar_day_event_entity(PDO $pdo, int $eventId): string
{
    if ($eventId < 1) {
        throw new InvalidArgumentException('eventId must be positive');
    }

    $entityId = 'calendar_event:' . $eventId;

    create_entity($pdo, $entityId, 'entity_type_calendar_event');

    return $entityId;
}

function resolve_parent_week_for_calendar_day(
    PDO $pdo,
    string $parentWeekEntityId
): array {
    $stmt = $pdo->prepare("
        SELECT
            ce.id,
            ce.entity_id,
            ce.layer_id,
            ce.week_index,
            ce.chronology_address,
            COALESCE(ce.projection_entity_id, cepm.projection_entity_id) AS projection_entity_id
        FROM sxnzlfun_chrysalis.calendar_events ce
        LEFT JOIN sxnzlfun_chrysalis.calendar_event_projection_membership cepm
            ON cepm.calendar_event_id = ce.id
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

    if (!isset($row['id']) || (int) $row['id'] <= 0) {
        throw new RuntimeException('Invalid parent week: missing calendar_events.id');
    }

    if (($row['layer_id'] ?? null) !== 'calendar_layer_week') {
        throw new RuntimeException('Parent is not a week');
    }

    if ($row['week_index'] === null) {
        throw new RuntimeException('Parent week missing week_index');
    }

    if (!isset($row['chronology_address']) || trim((string) $row['chronology_address']) === '') {
        throw new RuntimeException('Invalid parent week: missing chronology_address');
    }

    if (!isset($row['projection_entity_id']) || trim((string) $row['projection_entity_id']) === '') {
        throw new RuntimeException('Invalid parent week: missing projection_entity_id');
    }

    return $row;
}

function find_calendar_day_for_week(
    PDO $pdo,
    int $weekCalendarEventId,
    int $dayIndex,
    string $projectionEntityId
): ?array {
    $stmt = $pdo->prepare("
        SELECT ce.*
        FROM sxnzlfun_chrysalis.calendar_events ce
        INNER JOIN sxnzlfun_chrysalis.calendar_event_projection_membership cepm
            ON cepm.calendar_event_id = ce.id
        WHERE ce.parent_event_id = :parent_event_id
          AND ce.day_index = :day_index
          AND ce.layer_id = 'calendar_layer_day'
          AND cepm.projection_entity_id = :projection_entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':parent_event_id' => $weekCalendarEventId,
        ':day_index' => $dayIndex,
        ':projection_entity_id' => $projectionEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function create_calendar_day(
    PDO $pdo,
    string $parentWeekEntityId,
    int $dayIndex,
    string $dayLabel,
    string $realDateId
): array {
    $parentWeekEntityId = trim($parentWeekEntityId);
    $dayLabel = trim($dayLabel);
    $realDateId = trim($realDateId);

    if ($parentWeekEntityId === '') {
        throw new InvalidArgumentException('parent_week_entity_id must be non-empty');
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

    $parentWeek = resolve_parent_week_for_calendar_day($pdo, $parentWeekEntityId);
    $weekCalendarEventId = (int) $parentWeek['id'];
    $weekIndex = (int) $parentWeek['week_index'];
    $parentChronologyAddress = trim((string) $parentWeek['chronology_address']);
    $projectionEntityId = trim((string) $parentWeek['projection_entity_id']);

    $startedTransaction = false;

    $existing = find_calendar_day_for_week(
        $pdo,
        $weekCalendarEventId,
        $dayIndex,
        $projectionEntityId
    );

    if ($existing !== null) {
        return $existing;
    }

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $eventId = next_calendar_event_id($pdo);
        $entityId = create_calendar_day_event_entity($pdo, $eventId);
        $chronologyAddress = $parentChronologyAddress . '.' . $dayIndex;

        $insert = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.calendar_events (
                entity_id,
                projection_entity_id,
                layer_id,
                event_id,
                summary,
                week_index,
                day_index,
                time_index,
                event_index,
                subevent_index,
                chronology_address,
                parent_event_id,
                real_date_start_id,
                created_at
            ) VALUES (
                :entity_id,
                :projection_entity_id,
                'calendar_layer_day',
                :event_id,
                :summary,
                :week_index,
                :day_index,
                NULL,
                NULL,
                NULL,
                :chronology_address,
                :parent_event_id,
                :real_date_start_id,
                NOW()
            )
        ");

        $insert->execute([
            ':entity_id' => $entityId,
            ':projection_entity_id' => $projectionEntityId,
            ':event_id' => $eventId,
            ':summary' => $dayLabel,
            ':week_index' => $weekIndex,
            ':day_index' => $dayIndex,
            ':chronology_address' => $chronologyAddress,
            ':parent_event_id' => $weekCalendarEventId,
            ':real_date_start_id' => $realDateId,
        ]);

        $calendarEventId = (int) $pdo->lastInsertId();

        if ($calendarEventId <= 0) {
            throw new RuntimeException('Failed to create calendar event');
        }

        $membership = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.calendar_event_projection_membership (
                calendar_event_id,
                projection_entity_id,
                created_at
            ) VALUES (
                :calendar_event_id,
                :projection_entity_id,
                NOW()
            )
        ");

        $membership->execute([
            ':calendar_event_id' => $calendarEventId,
            ':projection_entity_id' => $projectionEntityId,
        ]);

        $select = $pdo->prepare("
            SELECT *
            FROM sxnzlfun_chrysalis.calendar_events
            WHERE id = :id
            LIMIT 1
        ");

        $select->execute([':id' => $calendarEventId]);

        $row = $select->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException('Failed to fetch created calendar day');
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $row;

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $existing = find_calendar_day_for_week(
            $pdo,
            $weekCalendarEventId,
            $dayIndex,
            $projectionEntityId
        );

        if ($existing !== null) {
            return $existing;
        }

        throw new RuntimeException(
            'Failed to create calendar day: ' . $e->getMessage(),
            0,
            $e
        );
    }
}
