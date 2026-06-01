<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';
require_once __DIR__ . '/calendar_event_projection_membership_service.php';
require_once __DIR__ . '/calendar_projection_materializer.php';

/**
 * Canonical Book event creation.
 *
 * Book events localize through:
 *
 *   calendar_book_times.id
 *       -> calendar_events.book_time_id
 *
 * not through recursive calendar_events parentage.
 */
function ensure_calendar_book_event(
    PDO $pdo,
    int|string $bookTimeIdentity,
    ?int $eventIndex,
    array $payload
): array {
    $bookTime = resolve_calendar_book_time($pdo, $bookTimeIdentity);

    $projectionId = (int)($bookTime['projection_id'] ?? 0);
    $bookTimeId = (int)($bookTime['id'] ?? 0);

    if ($projectionId < 1) {
        throw new RuntimeException('calendar_book_time missing projection_id');
    }

    if ($bookTimeId < 1) {
        throw new RuntimeException('calendar_book_time missing id');
    }

    assert_calendar_projection_type(
        $pdo,
        $projectionId,
        'projection_type_book'
    );

    $eventIndex = $eventIndex
        ?? get_next_calendar_book_event_index(
            $pdo,
            $projectionId,
            $bookTimeId
        );

    if ($eventIndex < 1) {
        throw new InvalidArgumentException('event_index must be positive');
    }

    $existing = find_calendar_book_event(
        $pdo,
        $projectionId,
        $bookTimeId,
        $eventIndex
    );

    if ($existing !== null) {
        ensure_calendar_event_entity_exists($pdo, (int)$existing['id']);
        $existingId = (int)$existing['id'];

        ensure_calendar_book_event_projection_surfaces(
            $pdo,
            $existingId,
            $projectionId
        );

        return get_calendar_node_by_id($pdo, $existingId);
    }

    $payload['book_time_id'] = $bookTimeId;
    $payload['event_index'] = $eventIndex;

    /*
     * Compatibility only.
     *
     * While ux_calendar_structural_identity still exists, inserted rows still
     * need sequence_index. This is not canonical Book locality.
     */
    $compatibilitySequenceIndex = get_next_sequence_index(
        $pdo,
        $projectionId,
        'calendar_layer_event',
        null
    );

    $event = insert_calendar_node(
        $pdo,
        $projectionId,
        'calendar_layer_event',
        null,
        $compatibilitySequenceIndex,
        $payload
    );

    ensure_calendar_book_event_projection_surfaces(
        $pdo,
        (int)$event['id'],
        $projectionId
    );

    return get_calendar_node_by_id($pdo, (int)$event['id']);
}

function ensure_calendar_book_event_projection_surfaces(
    PDO $pdo,
    int $calendarEventId,
    int $bookProjectionId
): void {
    $projectionIds = [$bookProjectionId];
    $realtimeProjectionId = find_realtime_main_projection_id($pdo);

    if ($realtimeProjectionId !== null) {
        $projectionIds[] = $realtimeProjectionId;
    }

    $projectionIds = array_values(array_unique(array_map('intval', $projectionIds)));

    ensure_calendar_event_projection_memberships(
        $pdo,
        $calendarEventId,
        $projectionIds
    );

    foreach ($projectionIds as $projectionId) {
        rebuild_calendar_projection($pdo, $projectionId);
    }
}

function find_realtime_main_projection_id(PDO $pdo): ?int
{
    $stmt = $pdo->prepare(
        "
        SELECT id
        FROM sxnzlfun_chrysalis.calendar_projections
        WHERE projection_code = :projection_code
          AND projection_type_id = :projection_type_id
        LIMIT 1
        "
    );

    $stmt->execute([
        ':projection_code' => 'realtime_projection_main',
        ':projection_type_id' => 'projection_type_timeline_view',
    ]);

    $projectionId = $stmt->fetchColumn();

    if ($projectionId === false) {
        return null;
    }

    $projectionId = (int)$projectionId;

    return $projectionId > 0 ? $projectionId : null;
}

function resolve_calendar_book_time(
    PDO $pdo,
    int|string $identity
): array {
    if (is_int($identity) || ctype_digit((string)$identity)) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM sxnzlfun_chrysalis.calendar_book_times
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => (int)$identity,
        ]);
    } else {
        $entityId = trim((string)$identity);

        if ($entityId === '') {
            throw new InvalidArgumentException(
                'book time identity must be non-empty'
            );
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM sxnzlfun_chrysalis.calendar_book_times
            WHERE entity_id = :entity_id
            LIMIT 1
        ");

        $stmt->execute([
            ':entity_id' => $entityId,
        ]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('calendar_book_time not found');
    }

    return $row;
}

function find_calendar_book_event(
    PDO $pdo,
    int $projectionId,
    int $bookTimeId,
    int $eventIndex
): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE projection_id = :projection_id
          AND book_time_id = :book_time_id
          AND event_index = :event_index
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':book_time_id' => $bookTimeId,
        ':event_index' => $eventIndex,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function get_next_calendar_book_event_index(
    PDO $pdo,
    int $projectionId,
    int $bookTimeId
): int {
    $stmt = $pdo->prepare("
        SELECT MAX(event_index)
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE projection_id = :projection_id
          AND book_time_id = :book_time_id
          AND layer_id = 'calendar_layer_event'
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':book_time_id' => $bookTimeId,
    ]);

    $max = $stmt->fetchColumn();

    return $max ? ((int)$max + 1) : 1;
}

function assert_calendar_projection_type(
    PDO $pdo,
    int $projectionId,
    string $expectedProjectionTypeId
): void {
    if ($projectionId < 1) {
        throw new InvalidArgumentException('projection_id must be positive');
    }

    $stmt = $pdo->prepare("
        SELECT projection_type_id
        FROM sxnzlfun_chrysalis.calendar_projections
        WHERE id = :projection_id
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
    ]);

    $actualProjectionTypeId = $stmt->fetchColumn();

    if (
        !is_string($actualProjectionTypeId)
        || trim($actualProjectionTypeId) === ''
    ) {
        throw new RuntimeException(
            'Missing projection_type_id for projection ' . $projectionId
        );
    }

    $actualProjectionTypeId = trim($actualProjectionTypeId);

    if ($actualProjectionTypeId !== $expectedProjectionTypeId) {
        throw new RuntimeException(
            'Expected projection_type_id '
            . $expectedProjectionTypeId
            . '; got '
            . $actualProjectionTypeId
        );
    }
}
