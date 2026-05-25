<?php

declare(strict_types=1);

/**
 * Calendar event semantic metadata applier.
 *
 * Approved narrow mutation boundary for semantic narrative text surfaces on an
 * existing calendar event.
 *
 * Writes only:
 * - calendar_events.summary
 * - calendar_events.notes
 *
 * Does not write ontology linkage fields:
 * - calendar_events.beat_type_id
 * - calendar_events.class_type_id
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
        'semantic_text_surfaces' => [
            'calendar_events.summary',
            'calendar_events.notes',
        ],
        'ontology_linkage_fields_touched' => [],
        'updated_rows' => $stmt->rowCount(),
    ];
}
