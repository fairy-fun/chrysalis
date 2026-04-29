<?php

declare(strict_types=1);

function validate_real_date_start_id_exists(PDO $pdo, string $realDateStartId): void
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM sxnzlfun_chrysalis.dates
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->execute([
        ':id' => $realDateStartId,
    ]);

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

    if ($bookCode === '') {
        throw new InvalidArgumentException('book_code must be a non-empty string');
    }

    if ($weekIndex < 1) {
        throw new InvalidArgumentException('week_index must be a positive integer');
    }

    if ($weekLabel === '') {
        throw new InvalidArgumentException('week_label must be a non-empty string');
    }

    if ($realDateStartId === '') {
        throw new InvalidArgumentException('real_date_start_id must be a non-empty string');
    }

    validate_real_date_start_id_exists($pdo, $realDateStartId);
    $projectionEntityId = resolve_book_projection_entity_id($bookCode);

    $duplicate = $pdo->prepare(
        "SELECT ce.id
         FROM sxnzlfun_chrysalis.calendar_events ce
         INNER JOIN sxnzlfun_chrysalis.calendar_event_projection_membership cepm
            ON cepm.calendar_event_id = ce.id
         WHERE ce.week_index = :week_index
           AND ce.parent_event_id IS NULL
           AND cepm.projection_entity_id = :projection_entity_id
         LIMIT 1"
    );

    $duplicate->execute([
        ':week_index' => $weekIndex,
        ':projection_entity_id' => $projectionEntityId,
    ]);

    $existingId = $duplicate->fetchColumn();

    if ($existingId !== false) {
        throw new RuntimeException(
            'Calendar week already exists for book_code ' . $bookCode .
            ' and week_index ' . $weekIndex .
            ' as calendar_events.id ' . (string) $existingId
        );
    }

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $eventId = next_calendar_event_id($pdo);

        $insert = $pdo->prepare(
            "INSERT INTO sxnzlfun_chrysalis.calendar_events (
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
            '__pending_calendar_event__',
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
        )"
        );

        $insert->execute([
            ':event_id' => $eventId,
            ':summary' => $weekLabel,
            ':week_index' => $weekIndex,
            ':chronology_address' => (string) $weekIndex,
            ':real_date_start_id' => $realDateStartId,
        ]);

        $id = (int) $pdo->lastInsertId();

        if ($id <= 0) {
            throw new RuntimeException('Failed to create calendar week');
        }
        $eventId = next_calendar_event_id($pdo);
        $entityId = 'calendar_event:' . $eventId;

        $insert = $pdo->prepare(
            "INSERT INTO sxnzlfun_chrysalis.calendar_events (
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
        )"
        );

        $insert->execute([
            ':entity_id' => $entityId,
            ':event_id' => $eventId,
            ':summary' => $weekLabel,
            ':week_index' => $weekIndex,
            ':chronology_address' => (string) $weekIndex,
            ':real_date_start_id' => $realDateStartId,
        ]);

        $membership = $pdo->prepare(
            "INSERT INTO sxnzlfun_chrysalis.calendar_event_projection_membership (
                calendar_event_id,
                projection_entity_id,
                created_at
            ) VALUES (
                :calendar_event_id,
                :projection_entity_id,
                NOW()
            )"
        );

        $membership->execute([
            ':calendar_event_id' => $id,
            ':projection_entity_id' => $projectionEntityId,
        ]);

        $select = $pdo->prepare(
            "SELECT *
             FROM sxnzlfun_chrysalis.calendar_events
             WHERE id = :id
             LIMIT 1"
        );

        $select->execute([
            ':id' => $id,
        ]);

        $row = $select->fetch();

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

        throw $e;
    }
}