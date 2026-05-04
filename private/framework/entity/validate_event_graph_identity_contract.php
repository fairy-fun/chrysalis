<?php

declare(strict_types=1);

function count_bad_calendar_event_links(PDO $pdo): int
{
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM sxnzlfun_chrysalis.calendar_events ce
         LEFT JOIN sxnzlfun_chrysalis.entities e
           ON e.id = ce.entity_id
         WHERE e.id IS NULL
            OR e.entity_type_id <> CASE ce.layer_id
                WHEN 'calendar_layer_week' THEN 'entity_type_calendar_week'
                WHEN 'calendar_layer_day' THEN 'entity_type_calendar_day'
                WHEN 'calendar_layer_time' THEN 'entity_type_calendar_time'
                WHEN 'calendar_layer_event' THEN 'entity_type_calendar_event'
                WHEN 'calendar_layer_subevent' THEN 'entity_type_calendar_event'
                ELSE '__invalid_calendar_layer__'
            END"
    );

    $count = $stmt->fetchColumn();

    if ($count === false) {
        throw new RuntimeException('Unable to count bad calendar event links');
    }

    return (int)$count;
}

function count_bad_event_entities(PDO $pdo): int
{
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM sxnzlfun_chrysalis.entities e
         WHERE e.entity_type_id = 'entity_type_event'"
    );

    $count = $stmt->fetchColumn();

    if ($count === false) {
        throw new RuntimeException('Unable to count legacy event entities');
    }

    return (int)$count;
}

function validate_event_graph_identity_contract(PDO $pdo): void
{
    $badCalendarEventLinks = count_bad_calendar_event_links($pdo);
    $badEventEntities = count_bad_event_entities($pdo);

    if ($badCalendarEventLinks > 0 || $badEventEntities > 0) {
        throw new RuntimeException(
            'Event graph identity contract violated: ' .
            'calendar_events.entity_id must resolve to entities.id ' .
            'with entity_type_id matching calendar_events.layer_id, and entity_type_event ' .
            'must not be in active use.'
        );
    }
}