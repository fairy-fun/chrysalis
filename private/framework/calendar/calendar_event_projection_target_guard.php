<?php

declare(strict_types=1);

/**
 * Calendar Event Projection Target Guard
 *
 * Validates that projection targets attach only to real calendar event
 * entities.
 *
 * This guard validates by direct DB lookup.
 * It does not resolve or construct calendar identity.
 */

/**
 * @return array<string, mixed>
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
        SELECT
            ce.id,
            ce.entity_id,
            ce.event_id,
            ce.projection_entity_id,
            ce.layer_id,
            ce.parent_event_id,
            ce.sequence_index,
            e.entity_type_id
        FROM sxnzlfun_chrysalis.calendar_events ce
        INNER JOIN sxnzlfun_chrysalis.entities e
            ON e.id = ce.entity_id
        WHERE ce.entity_id = :entity_id
        LIMIT 1
        '
    );

    $stmt->execute([
        ':entity_id' => $targetEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'Invalid projection target: calendar event or entity record does not exist.'
        );
    }

    if ((int)$row['event_id'] !== $eventId) {
        throw new RuntimeException(
            'Invalid projection target: calendar event entity_id does not match event_id.'
        );
    }

    if (($row['entity_type_id'] ?? null) !== 'entity_type_calendar_event') {
        throw new RuntimeException(
            'Invalid projection target: projections may only target entity_type_calendar_event entities.'
        );
    }

    if (($row['layer_id'] ?? null) !== 'calendar_layer_event') {
        throw new RuntimeException(
            'Invalid projection target: projections may only target calendar_layer_event nodes.'
        );
    }

    if (
        !isset($row['projection_entity_id']) ||
        !is_string($row['projection_entity_id']) ||
        trim($row['projection_entity_id']) === ''
    ) {
        throw new RuntimeException(
            'Invalid projection target: calendar event projection_entity_id is malformed.'
        );
    }

    if (
        !array_key_exists('sequence_index', $row) ||
        $row['sequence_index'] === null ||
        !ctype_digit((string) $row['sequence_index']) ||
        (int)$row['sequence_index'] <= 0
    ) {
        throw new RuntimeException(
            'Invalid projection target: calendar event sequence_index is malformed.'
        );
    }

    if ($row['parent_event_id'] !== null) {
        if (
            !ctype_digit((string)$row['parent_event_id']) ||
            (int)$row['parent_event_id'] <= 0
        ) {
            throw new RuntimeException(
                'Invalid projection target: calendar event parent_event_id is malformed.'
            );
        }
    }

    return $row;
}