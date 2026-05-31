<?php

declare(strict_types=1);

function assert_calendar_beat_classset_integrity(PDO $pdo, string $schemaName): void
{
    $stmt = $pdo->query("
        SELECT domain_id
        FROM {$schemaName}.calendar_domain_beat_classset_map
        GROUP BY domain_id
        HAVING COUNT(*) > 1
    ");

    $duplicates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($duplicates)) {
        throw new RuntimeException('Duplicate domain classset mappings: ' . implode(', ', $duplicates));
    }

    $stmt = $pdo->query("
        SELECT DISTINCT m.classset_id
        FROM {$schemaName}.calendar_domain_beat_classset_map m
        LEFT JOIN {$schemaName}.calendar_beat_classsets c
            ON c.id = m.classset_id
        WHERE c.id IS NULL
    ");

    $invalidMappings = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($invalidMappings)) {
        throw new RuntimeException('Invalid domain to classset mappings: ' . implode(', ', $invalidMappings));
    }

    $stmt = $pdo->query("
        SELECT c.id
        FROM {$schemaName}.calendar_beat_classsets c
        LEFT JOIN {$schemaName}.cvt_calendar_beat_type bt
            ON bt.set_id = c.id
        GROUP BY c.id
        HAVING COUNT(bt.id) = 0
    ");

    $emptyClasssets = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($emptyClasssets)) {
        throw new RuntimeException('Classsets with no beat types: ' . implode(', ', $emptyClasssets));
    }

    $stmt = $pdo->query("
        SELECT c.id
        FROM {$schemaName}.calendar_beat_classsets c
        LEFT JOIN {$schemaName}.cvt_calendar_beat_type bt
            ON bt.set_id = c.id
            AND bt.code = 'transition'
        GROUP BY c.id
        HAVING COUNT(bt.id) = 0
    ");

    $missingTransition = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($missingTransition)) {
        throw new RuntimeException('Classsets missing transition: ' . implode(', ', $missingTransition));
    }

    $stmt = $pdo->query("
        SELECT bt.id
        FROM {$schemaName}.cvt_calendar_beat_type bt
        LEFT JOIN {$schemaName}.calendar_beat_classsets c
            ON c.id = bt.set_id
        WHERE c.id IS NULL
    ");

    $orphanedBeatTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($orphanedBeatTypes)) {
        throw new RuntimeException('Beat types reference missing classsets: ' . implode(', ', $orphanedBeatTypes));
    }
}
