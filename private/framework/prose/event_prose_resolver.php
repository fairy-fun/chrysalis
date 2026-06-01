<?php

declare(strict_types=1);

function resolve_event_prose(PDO $pdo, string $calendarEventEntityId): array
{
    $calendarEventEntityId = trim($calendarEventEntityId);

    if ($calendarEventEntityId === '') {
        throw new InvalidArgumentException(
            'calendar_event_entity_id is required'
        );
    }

    $eventStmt = $pdo->prepare("
        SELECT
            ce.id,
            ce.entity_id,
            ce.projection_id,
            ce.layer_id,
            ce.summary,
            ce.sequence_index,
            ce.event_index,
            ce.chronology_address,
            ce.real_date_start_id,
            start_date_lookup.date_value AS real_date_start,
            ce.real_date_end_id,
            end_date_lookup.date_value AS real_date_end
        FROM calendar_events ce
        LEFT JOIN dates start_date_lookup
            ON start_date_lookup.id = ce.real_date_start_id
        LEFT JOIN dates end_date_lookup
            ON end_date_lookup.id = ce.real_date_end_id
        WHERE ce.entity_id = :entity_id
          AND ce.layer_id = 'calendar_layer_event'
        LIMIT 1
    ");

    $eventStmt->execute([
        ':entity_id' => $calendarEventEntityId,
    ]);

    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($event)) {
        return [
            'status' => 'event_missing',
            'calendar_event_entity_id' => $calendarEventEntityId,
            'event' => null,
            'published_prose' => null,
            'next_action' => 'Resolve or create the requested calendar event before prose lookup.',
        ];
    }

    $publicationStmt = $pdo->prepare("
        SELECT
            pp.id AS prose_projection_id,
            pp.target_entity_id,
            pp.published_prose_draft_id,
            pp.projection_order,
            pp.role_id,
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pd.summary,
            pd.prose_body
        FROM prose_projections pp
        LEFT JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
        WHERE pp.target_entity_id = :target_entity_id
        ORDER BY pp.id ASC
    ");

    $publicationStmt->execute([
        ':target_entity_id' => $calendarEventEntityId,
    ]);

    $publications = $publicationStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($publications === []) {
        return [
            'status' => 'event_exists_no_prose',
            'event' => $event,
            'publication_candidates' => [],
            'published_prose' => null,
            'next_action' => 'Event exists, but no prose projection is attached.',
        ];
    }

    $published = array_values(array_filter(
        $publications,
        static fn (array $row): bool =>
            !empty($row['published_prose_draft_id'])
            && !empty($row['prose_draft_id'])
    ));

    if ($published === []) {
        return [
            'status' => 'publication_missing',
            'event' => $event,
            'publication_candidates' => $publications,
            'published_prose' => null,
            'next_action' => 'A prose projection exists, but none resolves to a published prose draft.',
        ];
    }

    if (count($published) > 1) {
        return [
            'status' => 'publication_ambiguous',
            'event' => $event,
            'publication_candidates' => $published,
            'published_prose' => null,
            'next_action' => 'Multiple published prose bindings exist for this event. Enforce a canonical prose projection role before resolving event prose.',
        ];
    }

    $publication = $published[0];

    if (trim((string) ($publication['prose_body'] ?? '')) === '') {
        return [
            'status' => 'publication_missing',
            'event' => $event,
            'publication' => $publication,
            'published_prose' => null,
            'next_action' => 'A published prose draft exists, but its prose_body is empty.',
        ];
    }

    return [
        'status' => 'prose_found',
        'event' => $event,
        'published_prose' => [
            'prose_projection_id' => $publication['prose_projection_id'],
            'prose_draft_id' => $publication['prose_draft_id'],
            'prose_entity_id' => $publication['prose_entity_id'],
            'title' => $publication['title'],
            'summary' => $publication['summary'],
            'prose_body' => $publication['prose_body'],
        ],
        'next_action' => null,
    ];
}
