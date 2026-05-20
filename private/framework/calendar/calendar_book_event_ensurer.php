<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';

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

        return get_calendar_node_by_id($pdo, (int)$existing['id']);
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

    return insert_calendar_node(
        $pdo,
        $projectionId,
        'calendar_layer_event',
        null,
        $compatibilitySequenceIndex,
        $payload
    );
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