<?php

declare(strict_types=1);

/**
 * Calendar event external identity invariant.
 *
 * Canonical model:
 *
 * id
 *   Internal structural row identity.
 *
 * event_id
 *   Stable business/story identity.
 *
 * entity_id
 *   Externalized namespaced form of the structural row identity.
 *
 * Invariant:
 *
 * entity_id = "calendar_event:{id}"
 */
function validate_calendar_event_identity(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT
            id,
            entity_id,
            event_id
        FROM calendar_events
        WHERE entity_id <> CONCAT('calendar_event:', id)
        LIMIT 20
    ");

    $badRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($badRows !== []) {
        throw new RuntimeException(
            'Calendar event external identity invariant failed: ' .
            json_encode($badRows, JSON_UNESCAPED_SLASHES)
        );
    }
}