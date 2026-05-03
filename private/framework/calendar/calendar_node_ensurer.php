<?php

declare(strict_types=1);

/**
 * ============================
 * PUBLIC PRIMITIVES
 * ============================
 */

function ensure_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    ?int $sequenceIndex,
    array $payload = []
): array {
    $projectionEntityId = trim($projectionEntityId);
    $layerId = trim($layerId);

    if ($projectionEntityId === '') {
        throw new InvalidArgumentException('projection_entity_id must be non-empty');
    }

    if ($layerId === '') {
        throw new InvalidArgumentException('layer_id must be non-empty');
    }

    if ($sequenceIndex !== null && $sequenceIndex < 1) {
        throw new InvalidArgumentException('sequence_index must be positive');
    }

    if (!isset($payload['summary']) || trim((string)$payload['summary']) === '') {
        throw new InvalidArgumentException('summary is required');
    }

    $payload = filter_calendar_node_payload($layerId, $payload);

    while (true) {
        $candidateIndex = $sequenceIndex ?? get_next_sequence_index(
            $pdo,
            $projectionEntityId,
            $layerId,
            $parentEventId
        );

        $existing = find_calendar_node(
            $pdo,
            $projectionEntityId,
            $layerId,
            $parentEventId,
            $candidateIndex
        );

        if ($existing !== null) {
            ensure_calendar_event_entity_exists($pdo, (int)$existing['event_id']);
            assert_calendar_node_entity_type_matches_layer($pdo, $existing);
            return $existing;
        }

        try {
            $inserted = insert_calendar_node(
                $pdo,
                $projectionEntityId,
                $layerId,
                $parentEventId,
                $candidateIndex,
                $payload
            );

            assert_calendar_node_entity_type_matches_layer($pdo, $inserted);

            return $inserted;
        } catch (PDOException $e) {
            if (!is_calendar_structural_duplicate_key($e)) {
                throw $e;
            }
            // retry
        }
    }
}

function require_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    int $sequenceIndex
): array {
    $node = find_calendar_node(
        $pdo,
        $projectionEntityId,
        $layerId,
        $parentEventId,
        $sequenceIndex
    );

    if ($node === null) {
        throw new RuntimeException('Required calendar node not found for structural identity');
    }

    ensure_calendar_event_entity_exists($pdo, (int)$node['event_id']);
    assert_calendar_node_entity_type_matches_layer($pdo, $node);

    return $node;
}

/**
 * ============================
 * LOOKUP
 * ============================
 */

function find_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    int $sequenceIndex
): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE projection_entity_id = :projection
          AND layer_id = :layer
          AND sequence_index = :seq
          AND parent_event_id_norm = IFNULL(:parent, 0)
        LIMIT 1
    ");

    $stmt->execute([
        ':projection' => trim($projectionEntityId),
        ':layer' => trim($layerId),
        ':seq' => $sequenceIndex,
        ':parent' => $parentEventId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * ============================
 * INSERT
 * ============================
 */

function insert_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    int $sequenceIndex,
    array $payload
): array {
    $started = !$pdo->inTransaction();

    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $eventId = generate_event_id($pdo);
        $entityId = calendar_event_entity_id($eventId);

        $entityTypeId = calendar_entity_type_for_layer($layerId);

        if ($entityTypeId === '') {
            throw new RuntimeException('Empty entity type for calendar node');
        }

        ensure_entity_row($pdo, $entityId, $entityTypeId);

        $stmt = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.calendar_events (
                entity_id,
                projection_entity_id,
                parent_event_id,
                layer_id,
                sequence_index,
                event_id,
                summary,
                real_date_start_id,
                real_date_end_id,
                time_label_id,
                event_type_id,
                location_id,
                domain_id,
                class_type_id,
                notes,
                source_document
            ) VALUES (
                :entity_id,
                :projection,
                :parent,
                :layer,
                :seq,
                :event_id,
                :summary,
                :real_date_start_id,
                :real_date_end_id,
                :time_label_id,
                :event_type_id,
                :location_id,
                :domain_id,
                :class_type_id,
                :notes,
                :source_document
            )
        ");

        $stmt->execute([
            ':entity_id' => $entityId,
            ':projection' => trim($projectionEntityId),
            ':parent' => $parentEventId,
            ':layer' => trim($layerId),
            ':seq' => $sequenceIndex,
            ':event_id' => $eventId,
            ':summary' => $payload['summary'] ?? null,
            ':real_date_start_id' => $payload['real_date_start_id'] ?? null,
            ':real_date_end_id' => $payload['real_date_end_id'] ?? null,
            ':time_label_id' => $payload['time_label_id'] ?? null,
            ':event_type_id' => $payload['event_type_id'] ?? null,
            ':location_id' => $payload['location_id'] ?? null,
            ':domain_id' => $payload['domain_id'] ?? null,
            ':class_type_id' => $payload['class_type_id'] ?? null,
            ':notes' => $payload['notes'] ?? null,
            ':source_document' => $payload['source_document'] ?? null,
        ]);

        $id = (int)$pdo->lastInsertId();

        if ($started) {
            $pdo->commit();
        }

        return get_calendar_node_by_id($pdo, $id);

    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * ============================
 * SUPPORT
 * ============================
 */

function calendar_entity_type_for_layer(string $layerId): string
{
    static $map = [
        'calendar_layer_week'  => 'entity_type_calendar_week',
        'calendar_layer_day'   => 'entity_type_calendar_day',
        'calendar_layer_time'  => 'entity_type_calendar_time',
        'calendar_layer_event' => 'entity_type_calendar_event',
    ];

    if (!isset($map[$layerId])) {
        throw new InvalidArgumentException("No entity type mapping for layer_id: {$layerId}");
    }

    return $map[$layerId];
}

function assert_calendar_node_entity_type_matches_layer(PDO $pdo, array $node): void
{
    $entityId = $node['entity_id'] ?? null;
    $layerId = $node['layer_id'] ?? null;

    if (!is_string($entityId) || trim($entityId) === '') {
        throw new RuntimeException('Calendar node has invalid entity_id');
    }

    if (!is_string($layerId) || trim($layerId) === '') {
        throw new RuntimeException('Calendar node has invalid layer_id');
    }

    $stmt = $pdo->prepare("
        SELECT entity_type_id
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $entityTypeId = $stmt->fetchColumn();

    if (!is_string($entityTypeId) || trim($entityTypeId) === '') {
        throw new RuntimeException("Missing entity_type_id for {$entityId}");
    }


    $entityTypeId = $stmt->fetchColumn();

    if (!is_string($entityTypeId) || trim($entityTypeId) === '') {
        throw new RuntimeException("Missing entity_type_id for {$entityId}");
    }

// 🔒 Optional hardening: restrict allowed calendar entity types
    /*$validEntityTypes = [
        'entity_type_calendar_week',
        'entity_type_calendar_day',
        'entity_type_calendar_time',
        'entity_type_calendar_event',
    ];

    if (!in_array($entityTypeId, $validEntityTypes, true)) {
        throw new RuntimeException(
            "Unsupported entity_type_id for calendar node: {$entityTypeId}"
        );
    }*/


    $expectedEntityTypeId = calendar_entity_type_for_layer(trim($layerId));

    if ($entityTypeId !== $expectedEntityTypeId) {
        throw new RuntimeException(
            "Calendar node mismatch: entity_type_id {$entityTypeId} is not valid for layer_id {$layerId}"
        );
    }
}

function filter_calendar_node_payload(string $layerId, array $payload): array
{
    $allowed = [
        'calendar_layer_week' => ['summary', 'real_date_start_id', 'real_date_end_id'],
        'calendar_layer_day' => ['summary', 'real_date_start_id', 'real_date_end_id'],
        'calendar_layer_time' => ['summary', 'time_label_id'],
        'calendar_layer_event' => [
            'summary',
            'event_type_id',
            'location_id',
            'domain_id',
            'class_type_id',
            'notes',
            'source_document'
        ],
    ];

    $allowedKeys = $allowed[$layerId] ?? ['summary'];

    return array_intersect_key($payload, array_flip($allowedKeys));
}

function get_next_sequence_index(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId
): int {
    $stmt = $pdo->prepare("
        SELECT MAX(sequence_index)
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE projection_entity_id = :projection
          AND layer_id = :layer
          AND parent_event_id_norm = IFNULL(:parent, 0)
    ");

    $stmt->execute([
        ':projection' => $projectionEntityId,
        ':layer' => $layerId,
        ':parent' => $parentEventId,
    ]);

    $max = $stmt->fetchColumn();

    return $max ? ((int)$max + 1) : 1;
}

/**
 * Only structural identity duplicate-key races are safe to retry.
 * Other duplicate-key errors must surface as integrity failures.
 */
function is_calendar_structural_duplicate_key(PDOException $e): bool
{
    $info = $e->errorInfo ?? null;

    if (!is_array($info) || !isset($info[1], $info[2])) {
        return false;
    }

    if ((int)$info[1] !== 1062) {
        return false;
    }

    return str_contains((string)$info[2], 'ux_calendar_structural_identity');
}

function ensure_calendar_event_entity_exists(PDO $pdo, int $eventId): void
{
    $stmt = $pdo->prepare("
        SELECT layer_id
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE event_id = :event_id
        LIMIT 1
    ");

    $stmt->execute([
        ':event_id' => $eventId,
    ]);

    $layerId = $stmt->fetchColumn();

    if (!is_string($layerId) || trim($layerId) === '') {
        throw new RuntimeException('Calendar event row not found for entity repair');
    }

    $entityTypeId = calendar_entity_type_for_layer(trim($layerId));
    $entityId = calendar_event_entity_id($eventId);

    $stmt = $pdo->prepare("
        SELECT entity_type_id
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $existingTypeId = $stmt->fetchColumn();

    if (is_string($existingTypeId) && $existingTypeId !== $entityTypeId) {
        throw new RuntimeException(
            "Calendar entity type mismatch for {$entityId}: expected {$entityTypeId}, found {$existingTypeId}"
        );
    }

    ensure_entity_row($pdo, $entityId, $entityTypeId);
}

function ensure_entity_row(PDO $pdo, string $entityId, string $entityTypeId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.entities (id, entity_type_id)
        VALUES (:id, :type)
        ON DUPLICATE KEY UPDATE id = id
    ");

    $stmt->execute([
        ':id' => $entityId,
        ':type' => $entityTypeId,
    ]);
}

function calendar_event_entity_id(int $eventId): string
{
    return 'calendar_event:' . $eventId;
}

function generate_event_id(PDO $pdo): int
{
    ensure_sequence_row($pdo);

    $pdo->exec("
        UPDATE sxnzlfun_chrysalis.calendar_event_sequence
        SET current_value = LAST_INSERT_ID(current_value + 1)
        WHERE id = 1
    ");

    return (int)$pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
}

function ensure_sequence_row(PDO $pdo): void
{
    $pdo->exec("
        INSERT INTO sxnzlfun_chrysalis.calendar_event_sequence (id, current_value)
        VALUES (1, 0)
        ON DUPLICATE KEY UPDATE current_value = current_value
    ");
}

function get_calendar_node_by_id(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Inserted node not found');
    }

    return $row;
}