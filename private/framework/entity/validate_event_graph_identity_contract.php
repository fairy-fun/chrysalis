<?php

declare(strict_types=1);

require_once __DIR__ . '/../calendar/calendar_node_ensurer.php';

function count_bad_calendar_event_links(PDO $pdo): int
{
    $repairStmt = $pdo->query(
        "SELECT id
         FROM sxnzlfun_chrysalis.calendar_events
         ORDER BY id"
    );

    foreach ($repairStmt->fetchAll(PDO::FETCH_COLUMN) as $calendarEventRowId) {
        ensure_calendar_event_entity_exists($pdo, (int) $calendarEventRowId);
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM sxnzlfun_chrysalis.calendar_events ce
         LEFT JOIN sxnzlfun_chrysalis.entities e
           ON e.id = ce.entity_id
         WHERE ce.entity_id <> CONCAT('calendar_event:', ce.id)
            OR e.id IS NULL
            OR e.entity_type_id NOT IN (
                'entity_type_calendar_week',
                'entity_type_calendar_day',
                'entity_type_calendar_time',
                'entity_type_calendar_event'
            )"
    );

    $count = $stmt->fetchColumn();

    if ($count === false) {
        throw new RuntimeException('Unable to count bad calendar event links');
    }

    return (int)$count;
}

function validate_event_graph_identity_contract(PDO $pdo): void
{
    $badCalendarEventLinks = count_bad_calendar_event_links($pdo);

    if ($badCalendarEventLinks > 0) {
        throw new RuntimeException(
            'Event graph identity contract violated: ' .
            'calendar_events.entity_id must equal calendar_event:{calendar_events.id}, ' .
            'resolve to entities.id, and use a canonical calendar entity type.'
        );
    }
}
