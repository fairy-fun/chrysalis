<?php

declare(strict_types=1);

/**
 * Calendar Event Projection Target Guard
 *
 * Validates that projection targets resolve to real event-layer calendar nodes.
 */

require_once __DIR__ . '/calendar_node_resolver.php';

/**
 * @return array<string, mixed>
 */
function require_calendar_event_projection_target_node(
    PDO $pdo,
    string $targetEntityId
): array {
    $prefix = 'calendar_event:';

    if (!str_starts_with($targetEntityId, $prefix)) {
        throw new InvalidArgumentException(
            'Invalid projection target: target_entity_id must use calendar_event:{event_id}.'
        );
    }

    $rawEventId = substr($targetEntityId, strlen($prefix));

    if ($rawEventId === '' || !ctype_digit($rawEventId)) {
        throw new InvalidArgumentException(
            'Invalid projection target: calendar_event entity id must contain a positive numeric event_id.'
        );
    }

    $eventId = (int) $rawEventId;

    if ($eventId <= 0) {
        throw new InvalidArgumentException(
            'Invalid projection target: event_id must be greater than zero.'
        );
    }

    $stmt = $pdo->prepare(
        '
        SELECT
            projection_entity_id,
            layer_id,
            parent_event_id,
            sequence_index
        FROM calendar_events
        WHERE event_id = :event_id
        LIMIT 1
        '
    );

    $stmt->execute([
        ':event_id' => $eventId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'Invalid projection target: calendar event does not exist.'
        );
    }

    if (
        !isset($row['projection_entity_id']) ||
        !is_string($row['projection_entity_id']) ||
        $row['projection_entity_id'] === '' ||
        !isset($row['layer_id']) ||
        !is_string($row['layer_id']) ||
        $row['layer_id'] === '' ||
        !array_key_exists('sequence_index', $row) ||
        $row['sequence_index'] === null ||
        !ctype_digit((string) $row['sequence_index'])
    ) {
        throw new RuntimeException(
            'Invalid projection target: calendar event structural identity is malformed.'
        );
    }

    if ($row['layer_id'] !== 'calendar_layer_event') {
        throw new RuntimeException(
            'Invalid projection target: projections may only target calendar_layer_event nodes.'
        );
    }

    $parentEventId = null;

    if ($row['parent_event_id'] !== null) {
        if (!ctype_digit((string) $row['parent_event_id']) || (int) $row['parent_event_id'] <= 0) {
            throw new RuntimeException(
                'Invalid projection target: calendar event parent identity is malformed.'
            );
        }

        $parentEventId = (int) $row['parent_event_id'];
    }

    return require_calendar_node(
        $pdo,
        $row['projection_entity_id'],
        $row['layer_id'],
        $parentEventId,
        (int) $row['sequence_index']
    );
}