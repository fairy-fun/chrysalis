<?php
declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';

function create_calendar_event_under_time_entity(
    PDO $pdo,
    string $timeEntityId,
    array $payload
): array {
    $timeEntityId = trim($timeEntityId);

    if ($timeEntityId === '') {
        throw new InvalidArgumentException('timeEntityId must be non-empty');
    }

    $sql = "
        SELECT
            e.entity_type_id,
            ce.layer_id,
            ce.projection_entity_id,
            ce.event_id
        FROM sxnzlfun_chrysalis.entities e
        INNER JOIN sxnzlfun_chrysalis.calendar_events ce
            ON ce.entity_id = e.id
        WHERE e.id = :time_entity_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':time_entity_id' => $timeEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        throw new RuntimeException(
            'Time entity not found or not a calendar node: ' . $timeEntityId
        );
    }

    if ($row['entity_type_id'] !== 'entity_type_calendar_time') {
        throw new RuntimeException(
            'Invalid parent entity_type_id; expected entity_type_calendar_time, got: ' . $row['entity_type_id']
        );
    }

    if ($row['layer_id'] !== 'calendar_layer_time') {
        throw new RuntimeException(
            'Invalid parent layer_id; expected calendar_layer_time, got: ' . $row['layer_id']
        );
    }

    if ($row['projection_entity_id'] === null || trim((string)$row['projection_entity_id']) === '') {
        throw new RuntimeException(
            'Invalid calendar_time node: missing projection_entity_id for entity ' . $timeEntityId
        );
    }

    return ensure_calendar_node(
        $pdo,
        (string)$row['projection_entity_id'],
        'calendar_layer_event',
        (int)$row['event_id'],
        null,
        $payload
    );
}