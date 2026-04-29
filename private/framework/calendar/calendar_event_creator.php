<?php

declare(strict_types=1);

function create_calendar_event(
    PDO $pdo,
    string $summary,
    int $weekIndex,
    ?int $dayIndex,
    ?int $timeIndex,
    ?int $eventIndex,
    ?int $subeventIndex,
    string $chronologyAddress,
    ?int $parentEventId
): array {
    if (trim($summary) === '') {
        throw new InvalidArgumentException('summary must be a non-empty string');
    }

    if ($weekIndex < 1) {
        throw new InvalidArgumentException('week_index must be a positive integer');
    }

    if (trim($chronologyAddress) === '') {
        throw new InvalidArgumentException('chronology_address must be a non-empty string');
    }

    $depth = substr_count($chronologyAddress, '.') + 1;

    if ($depth > 2 && $parentEventId === null) {
        throw new InvalidArgumentException(
            'Non-root calendar events must include parent_event_id'
        );
    }

    if ($parentEventId !== null) {
        validate_calendar_event_parent_exists($pdo, $parentEventId);
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO sxnzlfun_chrysalis.calendar_events (
                entity_id,
                summary,
                week_index,
                day_index,
                time_index,
                event_index,
                subevent_index,
                chronology_address,
                parent_event_id
            ) VALUES (
                '__pending_calendar_event__',
                :summary,
                :week_index,
                :day_index,
                :time_index,
                :event_index,
                :subevent_index,
                :chronology_address,
                :parent_event_id
            )"
        );

        $stmt->execute([
            ':summary' => trim($summary),
            ':week_index' => $weekIndex,
            ':day_index' => $dayIndex,
            ':time_index' => $timeIndex,
            ':event_index' => $eventIndex,
            ':subevent_index' => $subeventIndex,
            ':chronology_address' => trim($chronologyAddress),
            ':parent_event_id' => $parentEventId,
        ]);

        $eventId = (int) $pdo->lastInsertId();

        if ($eventId < 1) {
            throw new RuntimeException('Failed to read inserted calendar event id');
        }

        $stmt = $pdo->prepare(
            "UPDATE sxnzlfun_chrysalis.calendar_events
             SET entity_id = CONCAT('calendar_event:', id)
             WHERE id = :id"
        );

        $stmt->execute([
            ':id' => $eventId,
        ]);

        $stmt = $pdo->prepare(
            "SELECT *
             FROM sxnzlfun_chrysalis.calendar_events
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $eventId,
        ]);

        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($event)) {
            throw new RuntimeException('Failed to fetch created calendar event');
        }

        $pdo->commit();

        return $event;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function validate_calendar_event_parent_exists(PDO $pdo, int $parentEventId): void
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM sxnzlfun_chrysalis.calendar_events
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->execute([
        ':id' => $parentEventId,
    ]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException(
            'Invalid parent_event_id: no matching calendar_events.id = ' . $parentEventId
        );
    }
}