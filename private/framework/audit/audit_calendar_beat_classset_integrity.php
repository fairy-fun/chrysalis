<?php


declare(strict_types=1);

function assert_calendar_beat_classset_integrity(PDO $pdo, string $schemaName): void
{
    // --------------------------------------------------
    // 1. Event rows referencing missing event types
    // --------------------------------------------------
    $stmt = $pdo->query("
        SELECT DISTINCT e.event_type_id
        FROM {$schemaName}.calendar_events e
        LEFT JOIN {$schemaName}.calendar_event_type_classvals et
            ON et.id = e.event_type_id
        WHERE e.event_type_id IS NOT NULL
          AND e.event_type_id <> ''
          AND et.id IS NULL
    ");

    $missingEventTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($missingEventTypes)) {
        throw new RuntimeException(
            'Calendar events reference missing event types: ' . implode(', ', $missingEventTypes)
        );
    }

    // --------------------------------------------------
    // 2. Beat types referencing invalid classsets
    // --------------------------------------------------
    $stmt = $pdo->query("
        SELECT DISTINCT b.set_id
        FROM {$schemaName}.cvt_calendar_beat_type b
        LEFT JOIN {$schemaName}.calendar_beat_classsets c
            ON c.id = b.set_id
        WHERE c.id IS NULL
    ");

    $invalidSets = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($invalidSets)) {
        throw new RuntimeException(
            'Invalid beat type classset references: ' . implode(', ', $invalidSets)
        );
    }

    // --------------------------------------------------
    // 3. Event types referencing invalid classsets
    // --------------------------------------------------
    $stmt = $pdo->query("
        SELECT DISTINCT et.beat_classset_id
        FROM {$schemaName}.calendar_event_type_classvals et
        LEFT JOIN {$schemaName}.calendar_beat_classsets c
            ON c.id = et.beat_classset_id
        WHERE et.beat_classset_id IS NOT NULL
          AND et.beat_classset_id <> ''
          AND c.id IS NULL
    ");

    $invalidMappings = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($invalidMappings)) {
        throw new RuntimeException(
            'Invalid event_type→classset mappings: ' . implode(', ', $invalidMappings)
        );
    }
}
