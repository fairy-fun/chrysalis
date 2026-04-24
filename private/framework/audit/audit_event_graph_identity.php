<?php

declare(strict_types=1);

function audit_event_graph_identity(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $sql = "
        SELECT
            COUNT(*) AS bad_count
        FROM calendar_events AS ce
        LEFT JOIN entities AS e
            ON e.id = ce.subject_entity_id
        WHERE ce.subject_entity_id IS NULL
           OR e.id IS NULL
           OR e.entity_type_id <> 'entity_type_calendar_event'
    ";

    $badCalendarEventLinks = (int) $pdo->query($sql)->fetchColumn();

    if ($badCalendarEventLinks > 0) {
        $violations[] = [
            'violation_code' => 'invalid_calendar_event_subject_entity',
            'bad_count' => $badCalendarEventLinks,
            'rule' => 'calendar_events.subject_entity_id must resolve to entities.id with entity_type_id = entity_type_calendar_event',
        ];
    }

    $sql = "
        SELECT
            COUNT(*) AS bad_count
        FROM entities AS e
        WHERE e.entity_type_id = 'entity_type_event'
    ";

    $badLegacyEventEntities = (int) $pdo->query($sql)->fetchColumn();

    if ($badLegacyEventEntities > 0) {
        $violations[] = [
            'violation_code' => 'legacy_entity_type_event_in_active_use',
            'bad_count' => $badLegacyEventEntities,
            'rule' => 'entities.entity_type_id = entity_type_event must not be in active use',
        ];
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'bad_calendar_event_link_count' => $badCalendarEventLinks,
        'bad_legacy_event_entity_count' => $badLegacyEventEntities,
        'violations' => $violations,
    ];
}

function assert_event_graph_identity(PDO $pdo, string $schemaName): void
{
    $audit = audit_event_graph_identity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException('Event graph identity contract violated.');
}
