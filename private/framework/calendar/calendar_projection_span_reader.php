<?php

declare(strict_types=1);

function fetch_calendar_projection_by_id(PDO $pdo, int $projectionId): ?array
{
    $projectionStmt = $pdo->prepare(
        <<<'SQL'
SELECT
    id,
    entity_id,
    projection_code,
    projection_title,
    projection_type_id
FROM sxnzlfun_chrysalis.calendar_projections
WHERE id = :projection_id
LIMIT 1
SQL
    );

    $projectionStmt->execute([
        ':projection_id' => $projectionId,
    ]);

    $projection = $projectionStmt->fetch(PDO::FETCH_ASSOC);

    return is_array($projection) ? $projection : null;
}

function fetch_calendar_events_for_projection_span(
    PDO $pdo,
    int $projectionId,
    string $startDate,
    string $endDate
): array {
    $eventsStmt = $pdo->prepare(
        <<<'SQL'
SELECT
    event_row.entity_id,
    parent_row.entity_id AS parent_entity_id,
    cep.calendar_projection_id AS projection_id,
    event_row.layer_id,
    event_row.sequence_index,
    event_row.event_index,
    event_row.chronology_address,
    event_row.summary,
    event_row.notes,
    event_row.real_date_start_id,
    start_date_lookup.date_value AS real_date_start,
    event_row.real_date_end_id,
    end_date_lookup.date_value AS real_date_end,
    event_row.created_at,
    event_row.updated_at,
    EXISTS(
        SELECT 1
        FROM sxnzlfun_chrysalis.prose_projections pp
        INNER JOIN sxnzlfun_chrysalis.prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
        WHERE pp.target_entity_id = event_row.entity_id
          AND TRIM(COALESCE(pd.prose_body, '')) <> ''
    ) AS has_prose
FROM sxnzlfun_chrysalis.calendar_event_projections cep
INNER JOIN sxnzlfun_chrysalis.calendar_events event_row
    ON event_row.id = cep.calendar_event_id
LEFT JOIN sxnzlfun_chrysalis.calendar_events parent_row
    ON parent_row.id = event_row.parent_event_id
LEFT JOIN sxnzlfun_chrysalis.dates start_date_lookup
    ON start_date_lookup.id = event_row.real_date_start_id
LEFT JOIN sxnzlfun_chrysalis.dates end_date_lookup
    ON end_date_lookup.id = event_row.real_date_end_id
WHERE cep.calendar_projection_id = :projection_id
  AND event_row.layer_id = 'calendar_layer_event'
  AND COALESCE(
        DATE(cep.projection_starts_at),
        start_date_lookup.date_value
      ) <= :end_date
  AND COALESCE(
        DATE(cep.projection_ends_at),
        DATE(cep.projection_starts_at),
        end_date_lookup.date_value,
        start_date_lookup.date_value
      ) >= :start_date
ORDER BY
    COALESCE(
        DATE(cep.projection_starts_at),
        start_date_lookup.date_value
    ) ASC,
    COALESCE(
        DATE(cep.projection_ends_at),
        DATE(cep.projection_starts_at),
        end_date_lookup.date_value,
        start_date_lookup.date_value
    ) ASC,
    event_row.sequence_index ASC,
    event_row.entity_id ASC
SQL
    );

    $eventsStmt->execute([
        ':projection_id' => $projectionId,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($events)) {
        return [];
    }

    foreach ($events as &$event) {
        $event['projection_id'] = (int) $event['projection_id'];
        $event['sequence_index'] = $event['sequence_index'] === null
            ? null
            : (int) $event['sequence_index'];
        $event['event_index'] = $event['event_index'] === null
            ? null
            : (int) $event['event_index'];
        $event['has_prose'] = (bool) $event['has_prose'];
    }
    unset($event);

    return $events;
}
