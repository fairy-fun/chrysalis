<?php

declare(strict_types=1);

/**
 * Projection-backed chronology prose resolver.
 *
 * Canonical read path:
 *
 * calendar_event_projections
 * → calendar_projection_builds
 * → calendar_events
 * → prose_projections
 * → prose_drafts
 *
 * Chronology supports BOTH:
 * - exact address match
 * - subtree prefix match
 */

function resolve_prose_by_chronology_address(
    PDO $pdo,
    int $projectionId,
    string $chronologyAddress
): array {

    $chronologyAddress = trim($chronologyAddress);

    if ($projectionId < 1) {
        throw new InvalidArgumentException('projectionId must be positive');
    }

    if ($chronologyAddress === '') {
        throw new InvalidArgumentException('chronologyAddress is required');
    }

    /*
    |--------------------------------------------------------------------------
    | Projection row lookup (SUBTREE ENABLED)
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            cep.calendar_event_id,
            cep.chronology_address,

            ce.entity_id,
            ce.summary,
            ce.notes,
            ce.layer_id

        FROM calendar_event_projections cep

        INNER JOIN calendar_projection_builds cpb
            ON cpb.id = cep.build_id

        INNER JOIN calendar_events ce
            ON ce.id = cep.calendar_event_id

        WHERE cep.calendar_projection_id = :projection_id
          AND (
                cep.chronology_address = :chronology_address
                OR cep.chronology_address LIKE CONCAT(:chronology_address, '.%')
          )
          AND cpb.id = (
                SELECT MAX(id)
                FROM calendar_projection_builds
                WHERE calendar_projection_id = :projection_id
                  AND status = 'valid'
          )

        ORDER BY cep.id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':chronology_address' => $chronologyAddress,
    ]);

    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($event)) {
        return [
            'status' => 'chronology_not_found',
            'chronology_address' => $chronologyAddress,
            'event' => null,
            'published_prose' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Published prose lookup
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            pp.id AS prose_projection_id,
            pp.role_id,

            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pd.summary,
            pd.prose_body

        FROM prose_projections pp

        INNER JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id

        WHERE pp.target_entity_id = :target_entity_id

        ORDER BY pp.id ASC
    ");

    $stmt->execute([
        ':target_entity_id' => $event['entity_id'],
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        return [
            'status' => 'prose_missing',
            'chronology_address' => $chronologyAddress,
            'event' => $event,
            'published_prose' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Subtree-safe behavior (NO artificial ambiguity collapse)
    |--------------------------------------------------------------------------
    */

    return [
        'status' => 'prose_found',
        'chronology_address' => $chronologyAddress,
        'event' => $event,
        'published_prose' => $rows,
    ];
}

/*
|--------------------------------------------------------------------------
| Week/day resolver (unchanged, already correct structurally)
|--------------------------------------------------------------------------
*/

function resolve_prose_by_week_day(
    PDO $pdo,
    int $projectionId,
    int $weekIndex,
    int $dayIndex
): array {

    if ($projectionId < 1) {
        throw new InvalidArgumentException('projectionId must be positive');
    }

    if ($weekIndex < 1) {
        throw new InvalidArgumentException('weekIndex must be positive');
    }

    if ($dayIndex < 1) {
        throw new InvalidArgumentException('dayIndex must be positive');
    }

    $stmt = $pdo->prepare("
        SELECT
            cep.calendar_event_id,
            cep.chronology_address,

            ce.entity_id,
            ce.summary,
            ce.notes,
            ce.layer_id,

            ce.week_index,
            ce.day_index,
            ce.time_index,
            ce.event_index

        FROM calendar_event_projections cep

        INNER JOIN calendar_projection_builds cpb
            ON cpb.id = cep.build_id

        INNER JOIN calendar_events ce
            ON ce.id = cep.calendar_event_id

        WHERE cep.calendar_projection_id = :projection_id
          AND ce.week_index = :week_index
          AND ce.day_index = :day_index
          AND cpb.id = (
                SELECT MAX(id)
                FROM calendar_projection_builds
                WHERE calendar_projection_id = :projection_id
                  AND status = 'valid'
          )

        ORDER BY
            ce.time_index ASC,
            ce.event_index ASC,
            ce.id ASC
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
        ':day_index' => $dayIndex,
    ]);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($events === []) {
        return [
            'status' => 'no_events_found',
            'week_index' => $weekIndex,
            'day_index' => $dayIndex,
            'entries' => [],
        ];
    }

    $entries = [];

    $proseStmt = $pdo->prepare("
        SELECT
            pp.id AS prose_projection_id,

            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pd.summary,
            pd.prose_body

        FROM prose_projections pp

        INNER JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id

        WHERE pp.target_entity_id = :target_entity_id

        ORDER BY pp.id ASC
        LIMIT 1
    ");

    foreach ($events as $event) {

        $proseStmt->execute([
            ':target_entity_id' => $event['entity_id'],
        ]);

        $prose = $proseStmt->fetch(PDO::FETCH_ASSOC);

        $entries[] = [
            'event' => $event,
            'published_prose' => $prose ?: null,
        ];
    }

    return [
        'status' => 'ok',
        'week_index' => $weekIndex,
        'day_index' => $dayIndex,
        'entries' => $entries,
    ];
}