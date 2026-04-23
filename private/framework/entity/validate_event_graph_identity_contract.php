<?php

declare(strict_types=1);

function count_bad_calendar_event_links(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT COUNT(*)
         FROM sxnzlfun_chrysalis.calendar_events ce
         LEFT JOIN sxnzlfun_chrysalis.entities e
           ON e.id = ce.subject_entity_id
         WHERE ce.subject_entity_id IS NULL
            OR e.id IS NULL
            OR e.entity_type_id <> "entity_type_calendar_event"'
    );

    $count = $stmt->fetchColumn();

    if ($count === false) {
        throw new RuntimeException('Unable to count bad calendar event links');
    }

    return (int) $count;
}

function count_bad_event_entities(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT COUNT(*)
         FROM sxnzlfun_chrysalis.entities e
         WHERE e.entity_type_id = "entity_type_event"'
    );

    $count = $stmt->fetchColumn();

    if ($count === false) {
        throw new RuntimeException('Unable to count legacy event entities');
    }

    return (int) $count;
}

function validate_event_graph_identity_contract(PDO $pdo): void
{
    $badCalendarEventLinks = count_bad_calendar_event_links($pdo);
    $badEventEntities = count_bad_event_entities($pdo);

    if ($badCalendarEventLinks > 0 || $badEventEntities > 0) {
        throw new RuntimeException(
            'Event graph identity contract violated: ' .
            'calendar_events.subject_entity_id must resolve to entities.id ' .
            'with entity_type_calendar_event, and entity_type_event ' .
            'must not be in active use.'
        );
    }
}
