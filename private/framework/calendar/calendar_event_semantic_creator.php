<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';
require_once __DIR__ . '/calendar_hierarchy_validator.php';

function create_calendar_event_under_time_entity(
    PDO $pdo,
    string $timeEntityId,
    array $payload
): array {
    $row = _resolve_calendar_parent_node_for_event_creation(
        $pdo,
        $timeEntityId
    );

    assert_calendar_semantic_parent_child(
        (string)$row['entity_type_id'],
        'entity_type_calendar_event'
    );

    return ensure_calendar_node(
        $pdo,
        (string)$row['projection_entity_id'],
        'calendar_layer_event',
        (int)$row['event_id'],
        null,
        $payload
    );
}

function create_calendar_event_under_event_entity(
    PDO $pdo,
    string $parentEventEntityId,
    array $payload
): array {
    $row = _resolve_calendar_parent_node_for_event_creation(
        $pdo,
        $parentEventEntityId
    );

    assert_calendar_semantic_parent_child(
        (string)$row['entity_type_id'],
        'entity_type_calendar_event'
    );

    return ensure_calendar_node(
        $pdo,
        (string)$row['projection_entity_id'],
        'calendar_layer_event',
        (int)$row['event_id'],
        null,
        $payload
    );
}

/** @internal */
function _resolve_calendar_parent_node_for_event_creation(
    PDO $pdo,
    string $entityId
): array {
    $entityId = trim($entityId);

    if ($entityId === '') {
        throw new InvalidArgumentException('parent entity id must be non-empty');
    }

    $stmt = $pdo->prepare("
        SELECT
            e.id AS entity_id,
            e.entity_type_id,
            ce.layer_id,
            ce.projection_entity_id,
            ce.event_id
        FROM sxnzlfun_chrysalis.entities e
        INNER JOIN sxnzlfun_chrysalis.calendar_events ce
            ON ce.entity_id = e.id
        WHERE e.id = :entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        throw new RuntimeException(
            'Parent entity not found or not a calendar node: ' . $entityId
        );
    }

    if (
        $row['projection_entity_id'] === null ||
        trim((string)$row['projection_entity_id']) === ''
    ) {
        throw new RuntimeException(
            'Invalid parent calendar node: missing projection_entity_id for entity ' . $entityId
        );
    }

    if (!isset($row['event_id']) || (int)$row['event_id'] < 1) {
        throw new RuntimeException(
            'Invalid parent calendar node: missing valid event_id for entity ' . $entityId
        );
    }

    return $row;
}