<?php

declare(strict_types=1);

/**
 * Core structural ensure primitive.
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

    if (empty($payload['summary'])) {
        throw new InvalidArgumentException('summary is required');
    }

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
            ensure_calendar_event_entity_exists($pdo, (int) $existing['event_id']);
            return $existing;
        }

        try {
            return insert_calendar_node(
                $pdo,
                $projectionEntityId,
                $layerId,
                $parentEventId,
                $candidateIndex,
                $payload
            );
        } catch (PDOException $e) {
            if (!is_duplicate_key($e)) {
                throw $e;
            }
        }
    }
}

/**
 * Required structural lookup.
 */
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

    return $node;
}

/**
 * Pure structural lookup matching canonical identity.
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
 * Only INSERT path into calendar_events.
 */
function insert_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    int $sequenceIndex,
    array $payload
): array {
    $startedTransaction = !$pdo->inTransaction();

    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $eventId = generate_event_id($pdo);
        $entityId = calendar_event_entity_id($eventId);

        ensure_entity_row($pdo, $entityId, 'entity_type_calendar_event');

        $stmt = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.calendar_events (
                entity_id,
                projection_entity_id,
                parent_event_id,
                layer_id,
                sequence_index,
                event_id,
                summary,
                real_date_start_id
            ) VALUES (
                :entity_id,
                :projection,
                :parent,
                :layer,
                :seq,
                :event_id,
                :summary,
                :real_date_start_id
            )
        ");

        $stmt->execute([
            ':entity_id' => $entityId,
            ':projection' => trim($projectionEntityId),
            ':parent' => $parentEventId,
            ':layer' => trim($layerId),
            ':seq' => $sequenceIndex,
            ':event_id' => $eventId,
            ':summary' => $payload['summary'],
            ':real_date_start_id' => $payload['real_date_start_id'] ?? null,
        ]);

        $id = (int) $pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }

        return get_calendar_node_by_id($pdo, $id);
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Append mode only.
 */
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
        ':projection' => trim($projectionEntityId),
        ':layer' => trim($layerId),
        ':parent' => $parentEventId,
    ]);

    $max = $stmt->fetchColumn();

    return ($max !== null && $max !== false) ? ((int) $max + 1) : 1;
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

    if (!is_array($row)) {
        throw new RuntimeException('Inserted calendar node not found');
    }

    return $row;
}

function ensure_calendar_event_entity_exists(PDO $pdo, int $eventId): void
{
    ensure_entity_row(
        $pdo,
        calendar_event_entity_id($eventId),
        'entity_type_calendar_event'
    );
}

function ensure_entity_row(PDO $pdo, string $entityId, string $entityTypeId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.entities (id, entity_type_id)
        VALUES (:id, :entity_type_id)
        ON DUPLICATE KEY UPDATE id = id
    ");

    $stmt->execute([
        ':id' => $entityId,
        ':entity_type_id' => $entityTypeId,
    ]);
}

function calendar_event_entity_id(int $eventId): string
{
    return 'calendar_event:' . $eventId;
}

function is_duplicate_key(PDOException $e): bool
{
    return $e->getCode() === '23000'
        || (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062);
}

function generate_event_id(PDO $pdo): int
{
    ensure_calendar_event_sequence_row($pdo);

    $affected = $pdo->exec("
        UPDATE sxnzlfun_chrysalis.calendar_event_sequence
        SET current_value = LAST_INSERT_ID(current_value + 1)
        WHERE id = 1
    ");

    if ($affected !== 1) {
        throw new RuntimeException('Failed to advance calendar_event_sequence');
    }

    $stmt = $pdo->query("SELECT LAST_INSERT_ID()");
    $eventId = (int) $stmt->fetchColumn();

    if ($eventId <= 0) {
        throw new RuntimeException('Invalid generated calendar event_id');
    }

    return $eventId;
}

function ensure_calendar_event_sequence_row(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.calendar_event_sequence (id, current_value)
        VALUES (1, 0)
        ON DUPLICATE KEY UPDATE current_value = current_value
    ");

    $stmt->execute();
}