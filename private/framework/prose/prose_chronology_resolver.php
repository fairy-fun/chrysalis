<?php

declare(strict_types=1);

require_once __DIR__ . '/story_surface_contract.php';

/**
 * Projection-backed chronology prose resolver.
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

        ORDER BY cep.chronology_address ASC, cep.id ASC
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':chronology_address' => $chronologyAddress,
    ]);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($events === []) {
        return build_chronology_surface(
            address: $chronologyAddress,
            projectionId: $projectionId,
            nodes: [],
            meta: [
                'is_subtree' => true,
                'prose_projection_count' => 0,
            ],
            status: 'chronology_not_found'
        );
    }

    $proseStmt = $pdo->prepare("
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

    $nodes = [];
    $proseProjectionCount = 0;

    foreach ($events as $event) {
        $proseStmt->execute([
            ':target_entity_id' => $event['entity_id'],
        ]);

        $publishedProse = $proseStmt->fetchAll(PDO::FETCH_ASSOC);
        $proseProjectionCount += count($publishedProse);

        $nodes[] = build_tier4_node(
            event: $event,
            publishedProse: $publishedProse !== [] ? $publishedProse : null,
            meta: [
                'chronology_address' => $event['chronology_address'],
            ]
        );
    }

    return build_chronology_surface(
        address: $chronologyAddress,
        projectionId: $projectionId,
        nodes: $nodes,
        meta: [
            'is_subtree' => true,
            'prose_projection_count' => $proseProjectionCount,
        ],
        status: $proseProjectionCount > 0 ? 'prose_found' : 'prose_missing'
    );
}

/*
|--------------------------------------------------------------------------
| Week/day resolver
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
        return build_chronology_surface(
            'week:' . $weekIndex . '/day:' . $dayIndex,
            $projectionId,
            [],
            [
                'week_index' => $weekIndex,
                'day_index' => $dayIndex,
                'prose_projection_count' => 0,
            ],
            'no_events_found'
        );
    }

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

    $nodes = [];
    $proseProjectionCount = 0;

    foreach ($events as $event) {
        $proseStmt->execute([
            ':target_entity_id' => $event['entity_id'],
        ]);

        $prose = $proseStmt->fetch(PDO::FETCH_ASSOC);
        $publishedProse = is_array($prose) ? $prose : null;

        if ($publishedProse !== null) {
            $proseProjectionCount++;
        }

        $nodes[] = build_tier4_node(
            event: $event,
            publishedProse: $publishedProse,
            meta: [
                'chronology_address' => $event['chronology_address'],
            ]
        );
    }

    return build_chronology_surface(
        'week:' . $weekIndex . '/day:' . $dayIndex,
        $projectionId,
        $nodes,
        [
            'week_index' => $weekIndex,
            'day_index' => $dayIndex,
            'prose_projection_count' => $proseProjectionCount,
        ],
        'ok'
    );
}