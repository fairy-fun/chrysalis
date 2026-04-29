<?php

declare(strict_types=1);

require_once __DIR__ . '/../entities/entity_creator.php';
require_once __DIR__ . '/../procedures/calendar_event_id.php';

function validate_real_date_start_id_exists(PDO $pdo, string $realDateStartId): void
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

function resolve_book_projection_entity_id(string $bookCode): string
{
    $bookCode = trim($bookCode);

    if ($bookCode === '') {
        throw new InvalidArgumentException('book_code must be non-empty');
    }

    return 'book_projection_' . $bookCode;
}

function create_calendar_event_entity(PDO $pdo, int $eventId): string
{
    if ($eventId < 1) {
        throw new InvalidArgumentException('eventId must be positive');
    }

    $entityId = 'calendar_event:' . $eventId;

    create_entity($pdo, $entityId, 'entity_type_calendar_event');

    return $entityId;
}

function find_calendar_week_for_book(
    PDO $pdo,
    string $projectionEntityId,
    int $weekIndex
): ?array {
    $stmt = $pdo->prepare("
        SELECT ce.*
        FROM sxnzlfun_chrysalis.calendar_events ce
        INNER JOIN sxnzlfun_chrysalis.calendar_event_projection_membership cepm
            ON cepm.calendar_event_id = ce.id
        WHERE ce.week_index = :week_index
          AND ce.parent_event_id IS NULL
          AND ce.layer_id = 'calendar_layer_week'
          AND cepm.projection_entity_id = :projection_entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':week_index' => $weekIndex,
        ':projection_entity_id' => $projectionEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function create_calendar_week_for_book(
    PDO $pdo,
    string $bookCode,
    int $weekIndex,
    string $weekLabel,
    string $realDateStartId
): array {
    $bookCode = trim($bookCode);
    $weekLabel = trim($weekLabel);
    $realDateStartId = trim($realDateStartId);

    validate_real_date_start_id_exists($pdo, $realDateStartId);

    $projectionEntityId = resolve_book_projection_entity_id($bookCode);

    $startedTransaction = false;

    $existing = find_calendar_week_for_book(
        $pdo,
        $projectionEntityId,
        $weekIndex
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
        $entityId = create_calendar_event_entity($pdo, $eventId);

        $insert = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.calendar_events (
                entity_id,
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
                'calendar_layer_week',
                :event_id,
                :summary,
                :week_index,
                NULL,
                NULL,
                NULL,
                NULL,
                :chronology_address,
                NULL,
                :real_date_start_id,
                NOW()
            )
        ");

        $insert->execute([
            ':entity_id' => $entityId,
            ':event_id' => $eventId,
            ':summary' => $weekLabel,
            ':week_index' => $weekIndex,
            ':chronology_address' => (string) $weekIndex,
            ':real_date_start_id' => $realDateStartId,
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
            throw new RuntimeException('Failed to fetch created calendar week');
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $row;

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $existing = find_calendar_week_for_book(
            $pdo,
            $projectionEntityId,
            $weekIndex
        );

        if ($existing !== null) {
            return $existing;
        }

        throw new RuntimeException(
            'Failed to create calendar week: ' . $e->getMessage(),
            0,
            $e
        );
    }
}