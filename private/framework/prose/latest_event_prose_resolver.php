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
|
| projection_order is not publication authority.
|
| Until prose projection role ontology is fully normalised, this resolver
| refuses to choose between multiple published bindings.
|
*/

    $stmt = $pdo->prepare("
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

    $stmt->execute([
        ':target_entity_id' => $latestEvent['entity_id'],
    ]);

    $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($publications === []) {
        return [
            'status' => 'latest_event_exists_no_prose',
            'projection' => $projection,
            'latest_event' => $latestEvent,
            'publication_candidates' => [],
            'published_prose' => null,
            'next_action' => 'Latest event exists, but no prose projection is attached. Create or publish prose for this event.',
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
            'projection' => $projection,
            'latest_event' => $latestEvent,
            'publication_candidates' => $publications,
            'published_prose' => null,
            'next_action' => 'A prose projection exists, but none resolves to a published prose draft.',
        ];
    }

    // TODO:
    // Once prose projection role ontology is canonicalised,
    // replace broad ambiguity detection with role-constrained resolution.
    // Multiple published bindings may be valid if exactly one represents
    // canonical primary prose and the others represent export/commentary/variant roles.

    if (count($published) > 1) {
        return [
            'status' => 'publication_ambiguous',
            'projection' => $projection,
            'latest_event' => $latestEvent,
            'publication_candidates' => $published,
            'published_prose' => null,
            'next_action' => 'Multiple published prose bindings exist for this event. Add or enforce a canonical prose projection role before resolving latest-event prose.',
        ];
    }

    $publication = $published[0];

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