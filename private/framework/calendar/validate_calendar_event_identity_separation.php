<?php


declare(strict_types=1);

/**
 * Calendar event identity separation invariant.
 *
 * Final mental model:
 *
 * id        -> this row exists
 * entity_id -> this row's external identity
 * event_id  -> where this sits in the story structure
 *
 * These axes must remain distinct.
 */
function validate_calendar_event_identity_separation(PDO $pdo): void
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