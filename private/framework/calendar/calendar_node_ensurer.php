<?php

declare(strict_types=1);

/**
 * Calendar Event Projection Target Guard
 *
 * Projection targets may only point at real calendar_layer_event nodes.
 */

function require_calendar_event_projection_target_node(
    PDO $pdo,
    string $targetEntityId
): array {
    $targetEntityId = trim($targetEntityId);
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
        SELECT *
        FROM calendar_events
        WHERE event_id = :event_id
          AND entity_id = :entity_id
          AND layer_id = :layer_id
        LIMIT 1
        '
    );

    $stmt->execute([
        ':event_id' => $eventId,
        ':entity_id' => $targetEntityId,
        ':layer_id' => 'calendar_layer_event',
    ]);

    $node = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($node)) {
        throw new RuntimeException(
            'Invalid projection target: target must resolve to an existing calendar_layer_event node.'
        );
    }

    return $node;
}