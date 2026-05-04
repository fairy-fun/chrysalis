<?php

declare(strict_types=1);

function audit_event_graph_identity(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $sql = "
        SELECT COUNT(*) AS bad_count
        FROM calendar_events AS ce
        LEFT JOIN entities AS e
            ON e.id = ce.entity_id
        WHERE e.id IS NULL
           OR e.entity_type_id <> CASE ce.layer_id
                WHEN 'calendar_layer_week' THEN 'entity_type_calendar_week'
                WHEN 'calendar_layer_day' THEN 'entity_type_calendar_day'
                WHEN 'calendar_layer_time' THEN 'entity_type_calendar_time'
                WHEN 'calendar_layer_event' THEN 'entity_type_calendar_event'
               WHEN 'calendar_layer_subevent' THEN 'entity_type_calendar_event'
                ELSE '__invalid_calendar_layer__'
           END
    ";

    $badCalendarEventLinks = (int) $pdo->query($sql)->fetchColumn();

    if ($badCalendarEventLinks > 0) {
        $violations[] = [
            'violation_code' => 'invalid_calendar_event_entity',
            'bad_count' => $badCalendarEventLinks,
            'rule' => 'calendar_events.entity_id must resolve to entities.id with an entity_type_id matching calendar_events.layer_id',        ];
    }

    $sql = "
        SELECT COUNT(*) AS bad_count
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
