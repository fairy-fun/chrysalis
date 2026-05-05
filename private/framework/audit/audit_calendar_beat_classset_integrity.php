<?php


declare(strict_types=1);

function assert_calendar_beat_classset_integrity(PDO $pdo, string $schemaName): void
{
    // --------------------------------------------------
    // 1. Unmapped domains in calendar_events
    // --------------------------------------------------
    $stmt = $pdo->query("
        SELECT DISTINCT e.domain_id
        FROM {$schemaName}.calendar_events e
        LEFT JOIN {$schemaName}.calendar_domain_beat_classset_map m
            ON m.domain_id = e.domain_id
        WHERE e.domain_id IS NOT NULL
          AND e.domain_id <> ''
          AND m.domain_id IS NULL
    ");

    $unmapped = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($unmapped)) {
        throw new RuntimeException(
            'Unmapped calendar_event domains: ' . implode(', ', $unmapped)
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
    // 3. Mapping table referencing invalid classsets
    // --------------------------------------------------
    $stmt = $pdo->query("
        SELECT DISTINCT m.classset_id
        FROM {$schemaName}.calendar_domain_beat_classset_map m
        LEFT JOIN {$schemaName}.calendar_beat_classsets c
            ON c.id = m.classset_id
        WHERE c.id IS NULL
    ");

    $invalidMappings = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($invalidMappings)) {
        throw new RuntimeException(
            'Invalid domain→classset mappings: ' . implode(', ', $invalidMappings)
        );
    }
}