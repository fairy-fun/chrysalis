<?php

declare(strict_types=1);

require_once __DIR__ . '/../entities/entity_creator.php';
require_once __DIR__ . '/../procedures/calendar_event_id.php';

function create_calendar_time_event_entity(PDO $pdo, int $eventId): string
{
    if ($eventId < 1) {
        throw new InvalidArgumentException('eventId must be positive');
    }

    $entityId = 'calendar_event:' . $eventId;

    create_entity($pdo, $entityId, 'entity_type_calendar_event');

    return $entityId;
}

function resolve_parent_day_for_calendar_time(
    PDO $pdo,
    string $parentDayEntityId
): array {
    $stmt = $pdo->prepare("
        SELECT
            ce.id,
            ce.entity_id,
            ce.layer_id,
            ce.week_index,
            ce.day_index,
            ce.chronology_address,
            COALESCE(ce.projection_entity_id, cepm.projection_entity_id) AS projection_entity_id
        FROM sxnzlfun_chrysalis.calendar_events ce
        LEFT JOIN sxnzlfun_chrysalis.calendar_event_projection_membership cepm
            ON cepm.calendar_event_id = ce.id
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

    if (($row['layer_id'] ?? null) !== 'calendar_layer_day') {
        throw new RuntimeException('Parent is not a day');
    }

    if ($row['week_index'] === null || $row['day_index'] === null) {
        throw new RuntimeException('Parent day missing indexes');
    }

    if (empty($row['chronology_address'])) {
        throw new RuntimeException('Parent day missing chronology_address');
    }

    if (empty($row['projection_entity_id'])) {
        throw new RuntimeException('Parent day missing projection_entity_id');
    }

    return $row;
}

function find_calendar_time_for_day(
    PDO $pdo,
    int $dayCalendarEventId,
    int $timeIndex,
    string $projectionEntityId
): ?array {
    $stmt = $pdo->prepare("
        SELECT ce.*
        FROM sxnzlfun_chrysalis.calendar_events ce
        INNER JOIN sxnzlfun_chrysalis.calendar_event_projection_membership cepm
            ON cepm.calendar_event_id = ce.id
        WHERE ce.parent_event_id = :parent_event_id
          AND ce.time_index = :time_index
          AND ce.layer_id = 'calendar_layer_time'
          AND cepm.projection_entity_id = :projection_entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':parent_event_id' => $dayCalendarEventId,
        ':time_index' => $timeIndex,
        ':projection_entity_id' => $projectionEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function create_calendar_time(
    PDO $pdo,
    string $parentDayEntityId,
    int $timeIndex,
    ?string $timeLabel = null
): array {
    $parentDayEntityId = trim($parentDayEntityId);

    if ($parentDayEntityId === '') {
        throw new InvalidArgumentException('parent_day_entity_id must be non-empty');
    }

    if ($timeIndex < 1) {
        throw new InvalidArgumentException('time_index must be positive');
    }

    $parentDay = resolve_parent_day_for_calendar_time($pdo, $parentDayEntityId);

    $dayCalendarEventId = (int) $parentDay['id'];
    $weekIndex = (int) $parentDay['week_index'];
    $dayIndex = (int) $parentDay['day_index'];
    $parentChronologyAddress = trim((string) $parentDay['chronology_address']);
    $projectionEntityId = trim((string) $parentDay['projection_entity_id']);

    $existing = find_calendar_time_for_day(
        $pdo,
        $dayCalendarEventId,
        $timeIndex,
        $projectionEntityId
    );

    if ($existing !== null) {
        return $existing;
    }

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $eventId = next_calendar_event_id($pdo);
        $entityId = create_calendar_time_event_entity($pdo, $eventId);

        $chronologyAddress = $parentChronologyAddress . '.' . $timeIndex;

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
                created_at
            ) VALUES (
                :entity_id,
                :projection_entity_id,
                'calendar_layer_time',
                :event_id,
                :summary,
                :week_index,
                :day_index,
                :time_index,
                NULL,
                NULL,
                :chronology_address,
                :parent_event_id,
                NOW()
            )
        ");

        $insert->execute([
            ':entity_id' => $entityId,
            ':projection_entity_id' => $projectionEntityId,
            ':event_id' => $eventId,
            ':summary' => $timeLabel,
            ':week_index' => $weekIndex,
            ':day_index' => $dayIndex,
            ':time_index' => $timeIndex,
            ':chronology_address' => $chronologyAddress,
            ':parent_event_id' => $dayCalendarEventId,
        ]);

        $calendarEventId = (int) $pdo->lastInsertId();

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

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $row;

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $existing = find_calendar_time_for_day(
            $pdo,
            $dayCalendarEventId,
            $timeIndex,
            $projectionEntityId
        );

        if ($existing !== null) {
            return $existing;
        }

        throw new RuntimeException(
            'Failed to create calendar time: ' . $e->getMessage(),
            0,
            $e
        );
    }
}