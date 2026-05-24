<?php

declare(strict_types=1);

/**
 * Calendar event metadata applier.
 *
 * This is an approved narrow mutation boundary for editorial metadata on an
 * existing calendar event. It may update summary / notes only. It must not
 * create calendar rows, generate topology, alter book locality, touch
 * chronology_address, or call ensure_calendar_node.
 */
function apply_calendar_event_metadata(
    PDO $pdo,
    string $calendarEventEntityId,
    string $summary,
    string $notes
): array {

    $entityId = trim($calendarEventEntityId);
    $summary = trim($summary);
    $notes = trim($notes);

    if ($entityId === '') {
        throw new RuntimeException(
            'Missing calendar event entity_id for metadata apply'
        );
    }

    if ($summary === '') {
        throw new RuntimeException(
            'Missing calendar event summary for metadata apply'
        );
    }

    if ($notes === '') {
        throw new RuntimeException(
            'Missing calendar event notes for metadata apply'
        );
    }

    $stmt = $pdo->prepare("
        UPDATE calendar_events
        SET
            summary = :summary,
            notes = :notes
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
    ");

    $stmt->execute([
        ':summary' => $summary,
        ':notes' => $notes,
        ':entity_id' => $entityId,
    ]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException(
            'No calendar event metadata row updated for entity_id: ' . $entityId
        );
    }

    return [
        'calendar_event_entity_id' => $entityId,
        'summary' => $summary,
        'notes' => $notes,
        'updated_rows' => $stmt->rowCount(),
    ];
}
