<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';
require_once __DIR__ . '/calendar_hierarchy_validator.php';

function ensure_calendar_week(
    PDO $pdo,
    int|string $projectionIdentity,
    ?int $sequenceIndex,
    array $payload
): array {
    assert_calendar_parent_transition(null, 'calendar_layer_week');

    return ensure_calendar_node(
        $pdo,
        $projectionIdentity,
        'calendar_layer_week',
        null,
        $sequenceIndex,
        $payload
    );
}

function ensure_calendar_day(
    PDO $pdo,
    string $parentWeekEntityId,
    ?int $sequenceIndex,
    array $payload
): array {
    $parent = resolve_calendar_node_for_layer_wrapper($pdo, $parentWeekEntityId);

    assert_calendar_parent_transition($parent, 'calendar_layer_day');

    return ensure_calendar_node(
        $pdo,
        (int)$parent['projection_id'],
        'calendar_layer_day',
        (int)$parent['id'],
        $sequenceIndex,
        $payload
    );
}

function ensure_calendar_time(
    PDO $pdo,
    string $parentDayEntityId,
    ?int $sequenceIndex,
    array $payload
): array {
    $parent = resolve_calendar_node_for_layer_wrapper($pdo, $parentDayEntityId);

    assert_calendar_parent_transition($parent, 'calendar_layer_time');

    return ensure_calendar_node(
        $pdo,
        (int)$parent['projection_id'],
        'calendar_layer_time',
        (int)$parent['id'],
        $sequenceIndex,
        $payload
    );
}

function ensure_calendar_event(
    PDO $pdo,
    string $parentEntityId,
    ?int $sequenceIndex,
    array $payload
): array {
    $parent = resolve_calendar_node_for_layer_wrapper($pdo, $parentEntityId);

    // 1. Structural validation (layer → layer)
    assert_calendar_parent_transition($parent, 'calendar_layer_event');

    // 2. Semantic validation (type → type)
    assert_calendar_semantic_parent_child(
        (string)$parent['entity_type_id'],
        'entity_type_calendar_event'
    );

    // 3. Delegate to primitive
    return ensure_calendar_node(
        $pdo,
        (int)$parent['projection_id'],
        'calendar_layer_event',
        (int)$parent['id'], // ← structural key (correct)
        $sequenceIndex,
        $payload
    );
}

function ensure_calendar_subevent(
    PDO $pdo,
    string|int $parentEventIdentity,
    ?int $sequenceIndex,
    array $payload
): array {
    $parent = resolve_calendar_node_for_layer_wrapper($pdo, $parentEventIdentity);

    assert_calendar_parent_transition($parent, 'calendar_layer_subevent');

    assert_calendar_semantic_parent_child(
        (string)$parent['entity_type_id'],
        'entity_type_calendar_event'
    );

    return ensure_calendar_node(
        $pdo,
        (int)$parent['projection_id'],
        'calendar_layer_subevent',
        (int)$parent['id'],
        $sequenceIndex,
        $payload
    );
}


/** @internal */
function resolve_calendar_node_for_layer_wrapper(
    PDO $pdo,
    string|int $identity
): array {

    /*
    |--------------------------------------------------------------------------
    | Canonical runtime identity
    |--------------------------------------------------------------------------
    |
    | projection_id is now canonical runtime identity.
    | entity_id remains compatibility ingress only.
    |
    */

    $row = null;

    /*
    |--------------------------------------------------------------------------
    | Projection-first resolution
    |--------------------------------------------------------------------------
    */

    if (is_int($identity) || ctype_digit((string)$identity)) {

        $calendarEventId = (int)$identity;

        if ($calendarEventId > 0) {

            $stmt = $pdo->prepare("
            SELECT
                ce.*,
                e.entity_type_id
            FROM sxnzlfun_chrysalis.calendar_events ce
            INNER JOIN sxnzlfun_chrysalis.entities e
                ON e.id = ce.entity_id
            WHERE ce.id = :id
            LIMIT 1
        ");

            $stmt->execute([
                ':id' => $calendarEventId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Compatibility entity fallback
    |--------------------------------------------------------------------------
    */

    if (!$row) {

        $entityId = trim((string)$identity);

        if ($entityId === '') {
            throw new InvalidArgumentException(
                'parent identity must be non-empty'
            );
        }

        $stmt = $pdo->prepare("
            SELECT
                ce.*,
                e.entity_type_id
            FROM sxnzlfun_chrysalis.calendar_events ce
            INNER JOIN sxnzlfun_chrysalis.entities e
                ON e.id = ce.entity_id
            WHERE ce.entity_id = :entity_id
            LIMIT 1
        ");

        $stmt->execute([
            ':entity_id' => $entityId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!is_array($row)) {

        throw new RuntimeException(
            'Parent calendar node not found'
        );
    }

    assert_calendar_node_entity_type_matches_layer($pdo, $row);

    /*
    |--------------------------------------------------------------------------
    | Structural invariants
    |--------------------------------------------------------------------------
    */

    if (
        !isset($row['id']) ||
        (int)$row['id'] < 1
    ) {
        throw new RuntimeException(
            'Invalid calendar node: missing valid internal id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Transitional compatibility invariant
    |--------------------------------------------------------------------------
    |
    | projection_entity_id still exists for downstream compatibility,
    | but is no longer treated as canonical runtime identity.
    |
    */

    if (
        !isset($row['projection_id']) ||
        (int)$row['projection_id'] < 1
    ) {
        throw new RuntimeException(
            'Invalid calendar node: missing projection_id'
        );
    }

    return $row;
}
