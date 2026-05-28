<?php

declare(strict_types=1);

/**
 * Approved narrow mutation boundary for:
 *
 * calendar_events.location_id
 *
 * This function intentionally performs no semantic extraction.
 * It only applies an already-resolved canonical place reference.
 */
function apply_calendar_event_location(
    PDO $pdo,
    string $calendarEventEntityId,
    string $locationId
): array {

    $entityId = trim($calendarEventEntityId);
    $locationId = trim($locationId);

    if ($entityId === '') {
        throw new RuntimeException(
            'Missing calendar event entity_id for location apply'
        );
    }

    if ($locationId === '') {
        throw new RuntimeException(
            'Missing location_id for calendar event location apply'
        );
    }

    $stmt = $pdo->prepare("
        SELECT place_id
        FROM sxnzlfun_chrysalis.places
        WHERE place_id = :place_id
        LIMIT 1
    ");

    $stmt->execute([
        ':place_id' => $locationId,
    ]);

    $found = $stmt->fetchColumn();

    if (!is_string($found) || trim($found) === '') {
        throw new RuntimeException(
            'calendar_events.location_id must resolve to places.place_id: '
            . $locationId
        );
    }

    $update = $pdo->prepare("
        UPDATE calendar_events
        SET location_id = :location_id
        WHERE entity_id = :entity_id
          AND layer_id IN (
              'calendar_layer_event',
              'calendar_layer_subevent'
          )
        LIMIT 1
    ");

    $update->execute([
        ':location_id' => $locationId,
        ':entity_id' => $entityId,
    ]);

    if ($update->rowCount() < 1) {
        throw new RuntimeException(
            'No calendar event location_id row updated for entity_id: '
            . $entityId
        );
    }

    return [
        'calendar_event_entity_id' => $entityId,
        'location_id' => $locationId,
        'ontology_linkage_field' => 'calendar_events.location_id',
        'ontology_authority' => 'places.place_id',
        'updated_rows' => $update->rowCount(),
    ];
}
