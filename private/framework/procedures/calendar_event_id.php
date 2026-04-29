<?php

declare(strict_types=1);

function next_calendar_event_id(PDO $pdo): int
{
    $affected = $pdo->exec("
        UPDATE sxnzlfun_chrysalis.calendar_event_sequence
        SET current_value = LAST_INSERT_ID(current_value + 1)
        WHERE id = 1
    ");

    if ($affected !== 1) {
        throw new RuntimeException('Failed to allocate next calendar event_id');
    }

    $eventId = (int) $pdo->lastInsertId();

    if ($eventId < 1) {
        throw new RuntimeException('Invalid allocated calendar event_id');
    }

    return $eventId;
}