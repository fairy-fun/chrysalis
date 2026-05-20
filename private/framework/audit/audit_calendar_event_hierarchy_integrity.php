<?php

declare(strict_types=1);

function audit_calendar_event_hierarchy(PDO $pdo, string $schemaName): array{
    $violations = [];

    /*
    |--------------------------------------------------------------------------
    | Book chronology locality
    |--------------------------------------------------------------------------
    |
    | Chronology depth no longer implies recursive calendar_events parentage.
    | Book projection events localise through calendar_book_times via
    | calendar_events.book_time_id.
    |
    */

    $sql = <<<SQL
SELECT
    ce.id,
    ce.subject_entity_id,
    ce.parent_event_id,
    ce.book_time_id,
    ce.chronology_address,
    ce.summary,
    cp.projection_type_id
FROM {$schemaName}.calendar_events ce
INNER JOIN {$schemaName}.calendar_projections cp
    ON cp.id = ce.projection_id
WHERE cp.projection_type_id = 'projection_type_book'
  AND ce.layer_id = 'calendar_layer_event'
  AND ce.parent_event_id IS NULL
  AND ce.book_time_id IS NULL
SQL;

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $violations[] = [
            'violation_code' => 'book_calendar_event_missing_book_time',
            'calendar_event_id' => $row['id'],
            'subject_entity_id' => $row['subject_entity_id'],
            'chronology_address' => $row['chronology_address'],
            'summary' => $row['summary'],
            'rule' => 'Book projection calendar_layer_event rows without parent_event_id must have book_time_id',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Book time foreign locality
    |--------------------------------------------------------------------------
    */

    $sql = <<<SQL
SELECT
    ce.id,
    ce.subject_entity_id,
    ce.book_time_id,
    ce.chronology_address,
    ce.summary
FROM {$schemaName}.calendar_events ce
INNER JOIN {$schemaName}.calendar_projections cp
    ON cp.id = ce.projection_id
LEFT JOIN {$schemaName}.calendar_book_times cbt
    ON cbt.id = ce.book_time_id
WHERE cp.projection_type_id = 'projection_type_book'
  AND ce.book_time_id IS NOT NULL
  AND cbt.id IS NULL
SQL;

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $violations[] = [
            'violation_code' => 'book_calendar_event_invalid_book_time',
            'calendar_event_id' => $row['id'],
            'subject_entity_id' => $row['subject_entity_id'],
            'book_time_id' => $row['book_time_id'],
            'chronology_address' => $row['chronology_address'],
            'summary' => $row['summary'],
            'rule' => 'Book projection calendar events with book_time_id must reference calendar_book_times.id',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Non-Book projections must not use Book chronology containers
    |--------------------------------------------------------------------------
    */

    $sql = <<<SQL
SELECT
    ce.id,
    ce.subject_entity_id,
    ce.book_time_id,
    ce.chronology_address,
    ce.summary,
    cp.projection_type_id
FROM {$schemaName}.calendar_events ce
INNER JOIN {$schemaName}.calendar_projections cp
    ON cp.id = ce.projection_id
WHERE cp.projection_type_id <> 'projection_type_book'
  AND ce.book_time_id IS NOT NULL
SQL;

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $violations[] = [
            'violation_code' => 'non_book_calendar_event_has_book_time',
            'calendar_event_id' => $row['id'],
            'subject_entity_id' => $row['subject_entity_id'],
            'projection_type_id' => $row['projection_type_id'],
            'book_time_id' => $row['book_time_id'],
            'chronology_address' => $row['chronology_address'],
            'summary' => $row['summary'],
            'rule' => 'Only projection_type_book calendar events may use book_time_id',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Recursive narrative locality
    |--------------------------------------------------------------------------
    |
    | parent_event_id still represents narrative containment. When it exists,
    | the child chronology address must descend from the parent chronology
    | address.
    |
    */

    $sql = <<<SQL
SELECT
    child.id AS child_id,
    child.subject_entity_id AS child_subject_entity_id,
    child.chronology_address AS child_chronology_address,
    child.parent_event_id,
    parent.id AS parent_id,
    parent.chronology_address AS parent_chronology_address
FROM {$schemaName}.calendar_events child
JOIN {$schemaName}.calendar_events parent
    ON parent.id = child.parent_event_id
WHERE child.chronology_address LIKE '%.%'
  AND child.chronology_address NOT LIKE CONCAT(parent.chronology_address, '.%')
SQL;

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $violations[] = [
            'violation_code' => 'calendar_event_parent_address_mismatch',
            'calendar_event_id' => $row['child_id'],
            'subject_entity_id' => $row['child_subject_entity_id'],
            'chronology_address' => $row['child_chronology_address'],
            'parent_event_id' => $row['parent_event_id'],
            'parent_chronology_address' => $row['parent_chronology_address'],
            'rule' => 'calendar_events.chronology_address must descend from parent chronology_address when parent_event_id is present',
        ];
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function assert_calendar_event_hierarchy(PDO $pdo, string $schemaName): void
{
    $audit = audit_calendar_event_hierarchy($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Calendar event hierarchy audit failed: '
        . json_encode($audit, JSON_UNESCAPED_SLASHES)
    );
}
