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
        'ontology_linkage_fields_touched' => [
            'calendar_events.beat_type_id',
        ],
        'ontology_authority' => 'cvt_calendar_beat_type.id',
        'updated_rows' => $stmt->rowCount(),
    ];
}

/**
 * Resolve a semantic beat code through the event's authoritative beat classset.
 */
function resolve_calendar_event_beat_type_by_code(
    PDO $pdo,
    string $calendarEventEntityId,
    string $beatCode
): array {

    static $eventStmt = null;
    static $beatStmt = null;

    $entityId = trim($calendarEventEntityId);
    $beatCode = mb_strtolower(trim($beatCode));

    if ($entityId === '') {
        throw new RuntimeException(
            'Missing calendar event entity_id for beat code resolution'
        );
    }

    if ($beatCode === '') {
        throw new RuntimeException(
            'Missing beat code for calendar event beat resolution'
        );
    }

    if ($eventStmt === null) {
        $eventStmt = $pdo->prepare("
            SELECT
                COALESCE(
                    NULLIF(TRIM(et.beat_classset_id), ''),
                    'CLASSSET-CALENDAR-BEAT-001'
                ) AS beat_classset_id,
                cs.code AS beat_classset_code
            FROM calendar_events e
            LEFT JOIN calendar_event_type_classvals et
                ON et.id = e.event_type_id
            LEFT JOIN calendar_beat_classsets cs
                ON cs.id = COALESCE(
                    NULLIF(TRIM(et.beat_classset_id), ''),
                    'CLASSSET-CALENDAR-BEAT-001'
                )
            WHERE e.entity_id = :entity_id
              AND e.layer_id = 'calendar_layer_event'
            LIMIT 1
        ");
    }

    $eventStmt->execute([
        ':entity_id' => $entityId,
    ]);

    $eventRow = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($eventRow)) {
        throw new RuntimeException(
            'No calendar event found for beat code resolution: ' . $entityId
        );
    }

    $beatClasssetId = trim((string)($eventRow['beat_classset_id'] ?? ''));
    $beatClasssetCode = trim((string)($eventRow['beat_classset_code'] ?? ''));

    if ($beatClasssetId === '') {
        throw new RuntimeException(
            'Missing beat classset id for calendar event: ' . $entityId
        );
    }

    if ($beatClasssetCode === '') {
        throw new RuntimeException(
            'Missing beat classset code for calendar event classset: '
            . $beatClasssetId
        );
    }

    if ($beatStmt === null) {
        $beatStmt = $pdo->prepare("
            SELECT id
            FROM cvt_calendar_beat_type
            WHERE set_id = :set_id
              AND code = :code
            LIMIT 1
        ");
    }

    $beatStmt->execute([
        ':set_id' => $beatClasssetId,
        ':code' => $beatCode,
    ]);

    $beatTypeId = $beatStmt->fetchColumn();

    if (!is_string($beatTypeId) || $beatTypeId === '') {
        throw new RuntimeException(
            'Unknown beat code for classset '
            . $beatClasssetCode
            . ' (' . $beatClasssetId . '): '
            . $beatCode
        );
    }

    return [
        'calendar_event_entity_id' => $entityId,
        'beat_code' => $beatCode,
        'beat_type_id' => $beatTypeId,
        'beat_classset_id' => $beatClasssetId,
        'beat_classset_code' => $beatClasssetCode,
        'ontology_authority'
            => 'calendar_event_type_classvals.beat_classset_id -> cvt_calendar_beat_type (set_id, code)',
    ];
}

/**
 * Resolve and apply a semantic beat code through the event classset model.
 */
function apply_calendar_event_beat_code(
    PDO $pdo,
    string $calendarEventEntityId,
    string $beatCode
): array {

    $resolved = resolve_calendar_event_beat_type_by_code(
        $pdo,
        $calendarEventEntityId,
        $beatCode
    );

    $applied = apply_calendar_event_beat_type(
        $pdo,
        $calendarEventEntityId,
        (string)$resolved['beat_type_id']
    );

    return [
        'calendar_event_entity_id' => $calendarEventEntityId,
        'beat_code' => (string)$resolved['beat_code'],
        'beat_type_id' => (string)$resolved['beat_type_id'],
        'beat_classset_id' => (string)$resolved['beat_classset_id'],
        'beat_classset_code' => (string)$resolved['beat_classset_code'],
        'ontology_linkage_field' => 'calendar_events.beat_type_id',
        'ontology_linkage_fields_touched' => [
            'calendar_events.beat_type_id',
        ],
        'ontology_authority' => (string)$resolved['ontology_authority'],
        'updated_rows' => (int)($applied['updated_rows'] ?? 0),
    ];
}

/**
 * Apply canonical stable environment linkage to an existing calendar event.
 */
function apply_calendar_event_location_id(
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
        UPDATE calendar_events
        SET location_id = :location_id
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
    ");

    $stmt->execute([
        ':location_id' => $locationId,
        ':entity_id' => $entityId,
    ]);

    if ($stmt->rowCount() < 1) {
        $verifyStmt = $pdo->prepare("
            SELECT id
            FROM calendar_events
            WHERE entity_id = :entity_id
              AND layer_id = 'calendar_layer_event'
            LIMIT 1
        ");

        $verifyStmt->execute([
            ':entity_id' => $entityId,
        ]);

        $existingRow = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($existingRow)) {
            throw new RuntimeException(
                'No calendar event location row found for entity_id: ' . $entityId
            );
        }
    }

    return [
        'calendar_event_entity_id' => $entityId,
        'location_id' => $locationId,
        'ontology_linkage_field' => 'calendar_events.location_id',
        'ontology_authority' => 'entities.id',
        'updated_rows' => $stmt->rowCount(),
    ];
}
