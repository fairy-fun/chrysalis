<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_event_ontology_guards.php';

/**
 * Calendar event ontology applier.
 *
 * Approved narrow mutation boundary for canonical ontology linkage fields on
 * an existing calendar event.
 *
 * This function applies concrete beat type ids only:
 * - calendar_events.beat_type_id -> cvt_calendar_beat_type.id
 *
 * It must not write semantic text surfaces. Semantic text belongs in:
 * - calendar_events.summary
 * - calendar_events.notes
 *
 * It must not create calendar nodes, generate topology, alter book locality,
 * or touch chronology_address.
 */
function apply_calendar_event_beat_type(
    PDO $pdo,
    string $calendarEventEntityId,
    string $beatTypeId
): array {

    $entityId = trim($calendarEventEntityId);
    $beatTypeId = trim($beatTypeId);

    if ($entityId === '') {
        throw new RuntimeException(
            'Missing calendar event entity_id for beat type apply'
        );
    }

    assert_calendar_beat_type_id($pdo, $beatTypeId);

    $stmt = $pdo->prepare("
        UPDATE calendar_events
        SET beat_type_id = :beat_type_id
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
    ");

    $stmt->execute([
        ':beat_type_id' => $beatTypeId,
        ':entity_id' => $entityId,
    ]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException(
            'No calendar event beat_type_id row updated for entity_id: ' . $entityId
        );
    }

    return [
        'calendar_event_entity_id' => $entityId,
        'beat_type_id' => $beatTypeId,
        'ontology_linkage_field' => 'calendar_events.beat_type_id',
        'ontology_authority' => 'cvt_calendar_beat_type.id',
        'updated_rows' => $stmt->rowCount(),
    ];
}
