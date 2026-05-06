<?php


declare(strict_types=1);

/**
 * Calendar event external identity invariant.
 *
 * Canonical model:
 *
 * id
 *   Internal database row id.
 *
 * event_id
 *   Stable business/event identity.
 *
 * entity_id
 *   Externalized namespaced form of event_id.
 *
 * Invariant:
 *
 * entity_id = "calendar_event:{event_id}"
 */
function validate_calendar_event_identity(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT
            id,
            entity_id,
            event_id
        FROM calendar_events
        WHERE entity_id <> CONCAT('calendar_event:', event_id)
        LIMIT 20
    ");

    $badRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($badRows !== []) {
        throw new RuntimeException(
            'Calendar event identity separation failed: ' .
            json_encode($badRows, JSON_UNESCAPED_SLASHES)
        );
    }
}