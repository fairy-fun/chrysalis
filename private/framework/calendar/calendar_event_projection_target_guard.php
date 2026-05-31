<?php

declare(strict_types=1);

/**
 * Calendar Event Projection Target Guard
 *
 * Validates that prose projections attach only to real calendar event entities.
 *
 * Canonical identity:
 * - calendar_events.id is the structural node id.
 * - calendar_events.entity_id is "calendar_event:{calendar_events.id}".
 */
function require_calendar_event_projection_target_node(
    PDO $pdo,
    string $targetEntityId
): array {
    $targetEntityId = trim($targetEntityId);
    $prefix = 'calendar_event:';

    if (!str_starts_with($targetEntityId, $prefix)) {
        throw new InvalidArgumentException(
            'Invalid projection target: target_entity_id must use calendar_event:{calendar_events.id}.'
        );
    }

    $rawCalendarEventId = substr($targetEntityId, strlen($prefix));

    if ($rawCalendarEventId === '' || !ctype_digit($rawCalendarEventId)) {
        throw new InvalidArgumentException(
            'Invalid projection target: calendar_event entity id must contain a positive numeric calendar_events.id.'
        );
    }

    $calendarEventId = (int)$rawCalendarEventId;

    if ($calendarEventId <= 0) {
        throw new InvalidArgumentException(
            'Invalid projection target: calendar_events.id must be greater than zero.'
        );
    }

    $stmt = $pdo->prepare(
        '
        SELECT
            ce.id,
            ce.entity_id,
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

    if ((int)$row['id'] !== $calendarEventId) {
        throw new RuntimeException(
            'Invalid projection target: calendar event entity_id does not match calendar_events.id.'
        );
    }

    if (($row['entity_type_id'] ?? null) !== 'entity_type_calendar_event') {
        throw new RuntimeException(
            'Invalid projection target: projections may only target entity_type_calendar_event entities.'
        );
    }

    if (($row['layer_id'] ?? null) !== 'calendar_layer_event') {
        throw new RuntimeException(
            'Invariant violation: prose projections may only target calendar_layer_event nodes.'
        );
    }

    if (
        !array_key_exists('sequence_index', $row) ||
        $row['sequence_index'] === null ||
        !ctype_digit((string)$row['sequence_index']) ||
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
