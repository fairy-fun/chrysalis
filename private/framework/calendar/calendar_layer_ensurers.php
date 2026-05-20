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

    $projectionTypeId = resolve_calendar_projection_type_id(
        $pdo,
        (int)$parent['projection_id']
    );

    if (
        $projectionTypeId === 'projection_type_book'
        && ($parent['layer_id'] ?? null) === 'calendar_layer_time'
    ) {
        throw new RuntimeException(
            'Book projection events must be created through calendar_book_times using book_time_id + event_index, not legacy calendar_layer_time rows.'
        );
    }

    assert_calendar_parent_transition($parent, 'calendar_layer_event');

    assert_calendar_semantic_parent_child(
        (string)$parent['entity_type_id'],
        'entity_type_calendar_event'
    );

    return ensure_calendar_node(
        $pdo,
        (int)$parent['projection_id'],
        'calendar_layer_event',
        (int)$parent['id'],
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

    $row = null;

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

    if (
        !isset($row['id']) ||
        (int)$row['id'] < 1
    ) {
        throw new RuntimeException(
            'Invalid calendar node: missing valid internal id'
        );
    }

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

function resolve_calendar_projection_type_id(PDO $pdo, int $projectionId): string
{
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

    $projectionTypeId = $stmt->fetchColumn();

    if (!is_string($projectionTypeId) || trim($projectionTypeId) === '') {
        throw new RuntimeException(
            'Missing projection_type_id for calendar projection ' . $projectionId
        );
    }

    return trim($projectionTypeId);
}
