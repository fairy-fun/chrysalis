<?php

declare(strict_types=1);

/**
 * Resolve the published prose for the latest executable event
 * inside a projection.
 *
 * Read-only resolver.
 *
 * Canonical path:
 *
 * calendar_projections
 * → calendar_events(sequence_index)
 * → prose_projections(target_entity_id)
 * → prose_drafts(published_prose_draft_id)
 */
function resolve_latest_event_prose(PDO $pdo, int $projectionId): array
{
    if ($projectionId < 1) {
        throw new InvalidArgumentException(
            'projectionId must be a positive integer'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Projection exists
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            projection_code,
            projection_title
        FROM calendar_projections
        WHERE id = :projection_id
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
    ]);

    $projection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($projection)) {
        return [
            'status' => 'projection_missing',
            'projection_id' => $projectionId,
            'latest_event' => null,
            'published_prose' => null,
            'next_action' => 'Resolve or create the Book 1 projection context before prose lookup.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Latest executable event
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            ce.id,
            ce.entity_id,
            ce.summary,
            ce.sequence_index,
            ce.event_index,
            ce.chronology_address,
            ce.parent_event_id,
            ce.projection_id
        FROM calendar_events ce
        WHERE ce.projection_id = :projection_id
          AND ce.layer_id = 'calendar_layer_event'
        ORDER BY ce.sequence_index DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
    ]);

    $latestEvent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($latestEvent)) {
        return [
            'status' => 'latest_event_slot_inferable',
            'projection' => $projection,
            'latest_event' => null,
            'published_prose' => null,
            'next_action' => 'No executable event exists in this projection. Resolve hierarchy state and create the next event-layer node before prose lookup.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Published prose binding
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            pp.id AS prose_projection_id,
            pp.target_entity_id,
            pp.published_prose_draft_id,
            pp.projection_order,
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pd.summary,
            pd.prose_body
        FROM prose_projections pp
        LEFT JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
        WHERE pp.target_entity_id = :target_entity_id
        ORDER BY pp.projection_order ASC, pp.id ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':target_entity_id' => $latestEvent['entity_id'],
    ]);

    $publication = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($publication)) {
        return [
            'status' => 'latest_event_exists_no_prose',
            'projection' => $projection,
            'latest_event' => $latestEvent,
            'published_prose' => null,
            'next_action' => 'Latest event exists, but no prose projection is attached. Create or publish prose for this event.',
        ];
    }

    if (
        empty($publication['published_prose_draft_id']) ||
        empty($publication['prose_draft_id'])
    ) {
        return [
            'status' => 'publication_missing',
            'projection' => $projection,
            'latest_event' => $latestEvent,
            'publication' => $publication,
            'published_prose' => null,
            'next_action' => 'A prose projection exists, but it does not resolve to a published prose draft.',
        ];
    }

    if (trim((string)($publication['prose_body'] ?? '')) === '') {
        return [
            'status' => 'publication_missing',
            'projection' => $projection,
            'latest_event' => $latestEvent,
            'publication' => $publication,
            'published_prose' => null,
            'next_action' => 'A published prose draft exists, but its prose_body is empty.',
        ];
    }

    return [
        'status' => 'prose_found',
        'projection' => $projection,
        'latest_event' => $latestEvent,
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