<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';
require_once __DIR__ . '/calendar_hierarchy_validator.php';

function ensure_calendar_week(
    PDO $pdo,
    string $projectionEntityId,
    ?int $sequenceIndex,
    array $payload
): array {
    assert_calendar_parent_transition(null, 'calendar_layer_week');

    return ensure_calendar_node(
        $pdo,
        $projectionEntityId,
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
        (string)$parent['projection_entity_id'],
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
        (string)$parent['projection_entity_id'],
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
        (string)$parent['projection_entity_id'],
        'calendar_layer_event',
        (int)$parent['id'], // ← structural key (correct)
        $sequenceIndex,
        $payload
    );
}

/** @internal */
function resolve_calendar_node_for_layer_wrapper(
    PDO $pdo,
    string $entityId
): array {
    $entityId = trim($entityId);

    if ($entityId === '') {
        throw new InvalidArgumentException('parent entity id must be non-empty');
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

    if (!is_array($row)) {
        throw new RuntimeException(
            'Parent entity not found or not a calendar node: ' . $entityId
        );
    }

    assert_calendar_node_entity_type_matches_layer($pdo, $row);

    if (
        !isset($row['id']) ||
        (int)$row['id'] < 1
    ) {
        throw new RuntimeException(
            'Invalid parent calendar node: missing valid internal id for entity ' . $entityId
        );
    }

    if (
        !isset($row['projection_entity_id']) ||
        trim((string)$row['projection_entity_id']) === ''
    ) {
        throw new RuntimeException(
            'Invalid parent calendar node: missing projection_entity_id for entity ' . $entityId
        );
    }

    return $row;
}
