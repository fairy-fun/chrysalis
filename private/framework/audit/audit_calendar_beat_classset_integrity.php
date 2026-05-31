<?php


declare(strict_types=1);

function assert_calendar_beat_classset_integrity(PDO $pdo, string $schemaName): void
{
    // --------------------------------------------------
    // 1. Beat types referencing invalid classsets
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
    // 2. Mapping table referencing invalid classsets
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
