<?php

declare(strict_types=1);

function audit_event_graph_identity(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $sql = "
        SELECT
            ce.id,
            ce.entity_id,
            ce.layer_id,
            e.entity_type_id
        FROM {$schemaName}.calendar_events AS ce
        LEFT JOIN {$schemaName}.entities AS e
            ON e.id = ce.entity_id
        WHERE ce.entity_id <> CONCAT('calendar_event:', ce.id)
           OR e.id IS NULL
           OR e.entity_type_id NOT IN (
                'entity_type_calendar_week',
                'entity_type_calendar_day',
                'entity_type_calendar_time',
                'entity_type_calendar_event'
           )
        ORDER BY ce.id
        LIMIT 20
    ";

    $badCalendarEventRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $badCalendarEventLinks = count($badCalendarEventRows);

    if ($badCalendarEventLinks > 0) {
        $violations[] = [
            'violation_code' => 'invalid_calendar_event_entity',
            'bad_count' => $badCalendarEventLinks,
            'rule' => 'calendar_events.entity_id must equal calendar_event:{calendar_events.id}, resolve to entities.id, and use a canonical calendar entity type',
            'sample_rows' => $badCalendarEventRows,
        ];
    }

    $sql = "
        SELECT
            e.id,
            e.entity_type_id
        FROM {$schemaName}.entities AS e
        WHERE e.entity_type_id = 'entity_type_event'
        ORDER BY e.id
        LIMIT 20
    ";

    $badLegacyEventRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $badLegacyEventEntities = count($badLegacyEventRows);

    if ($badLegacyEventEntities > 0) {
        $violations[] = [
            'violation_code' => 'legacy_entity_type_event_in_active_use',
            'bad_count' => $badLegacyEventEntities,
            'rule' => 'entities.entity_type_id = entity_type_event must not be in active use',
            'sample_rows' => $badLegacyEventRows,
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

    throw new RuntimeException(
        'Event graph identity contract violated: '
        . json_encode($audit, JSON_UNESCAPED_SLASHES)
    );
}
