<?php

declare(strict_types=1);

function audit_calendar_event_hierarchy(PDO $pdo, string $schemaName): array{
    $violations = [];

    $sql = <<<SQL
SELECT
    id,
    subject_entity_id,
    parent_event_id,
    chronology_address,
    summary
FROM {$schemaName}.calendar_events
WHERE parent_event_id IS NULL
  AND chronology_address LIKE '%.%.%'
SQL;

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $violations[] = [
            'violation_code' => 'calendar_event_missing_parent',
            'calendar_event_id' => $row['id'],
            'subject_entity_id' => $row['subject_entity_id'],
            'chronology_address' => $row['chronology_address'],
            'summary' => $row['summary'],
            'rule' => 'calendar_events with chronology depth 3+ must have parent_event_id',
        ];
    }

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
            'rule' => 'calendar_events.chronology_address must descend from parent chronology_address',
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