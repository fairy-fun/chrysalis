<?php

declare(strict_types=1);

/**
 * Core structural ensure primitive
 *
 * RULES:
 * - Only place allowed to INSERT into calendar_events
 * - Idempotent via structural identity
 * - Safe under concurrency
 * - First write wins (no updates to calendar_events)
 * - Every calendar_event must have a matching entities row
 */
function ensure_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    ?int $sequenceIndex,
    array $payload = []
): array {
    if (empty($payload['summary'])) {
        throw new InvalidArgumentException('summary is required');
    }

    while (true) {
        $candidateIndex = ($sequenceIndex !== null)
            ? $sequenceIndex
            : get_next_sequence_index($pdo, $projectionEntityId, $layerId, $parentEventId);

        $existing = find_calendar_node(
            $pdo,
            $projectionEntityId,
            $layerId,
            $parentEventId,
            $candidateIndex
        );

        if ($existing !== null) {
            ensure_calendar_event_entity_exists($pdo, (int)$existing['event_id']);
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

            continue;
        }
    }
}

/**
 * Structural lookup.
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
        FROM calendar_events
        WHERE projection_entity_id = :projection
          AND layer_id = :layer
          AND COALESCE(parent_event_id, 0) = COALESCE(:parent, 0)
          AND sequence_index = :seq
        LIMIT 1
    ");

    $stmt->execute([
        ':projection' => $projectionEntityId,
        ':layer' => $layerId,
        ':parent' => $parentEventId,
        ':seq' => $sequenceIndex,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Insert node.
 *
 * This is the ONLY write path into calendar_events.
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
            INSERT INTO calendar_events (
                entity_id,
                projection_entity_id,
                parent_event_id,
                layer_id,
                sequence_index,
                event_id,
                summary
            ) VALUES (
                :entity_id,
                :projection,
                :parent,
                :layer,
                :seq,
                :event_id,
                :summary
            )
        ");

        $stmt->execute([
            ':entity_id' => $entityId,
            ':projection' => $projectionEntityId,
            ':parent' => $parentEventId,
            ':layer' => $layerId,
            ':seq' => $sequenceIndex,
            ':event_id' => $eventId,
            ':summary' => $payload['summary'],
        ]);

        $id = (int)$pdo->lastInsertId();

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
 * Append index.
 *
 * Safe because callers retry on duplicate.
 * Append mode must recompute this value on retry.
 */
function get_next_sequence_index(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId
): int {
    $stmt = $pdo->prepare("
        SELECT MAX(sequence_index)
        FROM calendar_events
        WHERE projection_entity_id = :projection
          AND layer_id = :layer
          AND parent_event_id <=> :parent
    ");

    $stmt->execute([
        ':projection' => $projectionEntityId,
        ':layer' => $layerId,
        ':parent' => $parentEventId,
    ]);

    $max = $stmt->fetchColumn();

    return ($max !== null && $max !== false) ? ((int)$max + 1) : 1;
}

/**
 * Fetch by primary key.
 */
function get_calendar_node_by_id(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_events
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Inserted calendar node not found');
    }

    return $row;
}

/**
 * Ensure entity exists for an existing calendar event.
 *
 * Used on idempotent return to repair/guarantee entity consistency.
 */
function ensure_calendar_event_entity_exists(PDO $pdo, int $eventId): void
{
    ensure_entity_row(
        $pdo,
        calendar_event_entity_id($eventId),
        'entity_type_calendar_event'
    );
}

/**
 * Ensure global entity row exists.
 */
function ensure_entity_row(PDO $pdo, string $entityId, string $entityTypeId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO entities (id, entity_type_id)
        VALUES (:id, :entity_type_id)
        ON DUPLICATE KEY UPDATE id = id
    ");

    $stmt->execute([
        ':id' => $entityId,
        ':entity_type_id' => $entityTypeId,
    ]);
}

/**
 * Deterministic calendar event entity identity.
 */
function calendar_event_entity_id(int $eventId): string
{
    return 'calendar_event:' . $eventId;
}

/**
 * Proper duplicate detection for MySQL/MariaDB.
 */
function is_duplicate_key(PDOException $e): bool
{
    return $e->getCode() === '23000'
        || (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062);
}

/**
 * Generate event_id using calendar_event_sequence.
 */
function generate_event_id(PDO $pdo): int
{
    ensure_calendar_event_sequence_row($pdo);

    $affected = $pdo->exec("
        UPDATE calendar_event_sequence
        SET current_value = LAST_INSERT_ID(current_value + 1)
        WHERE id = 1
    ");

    if ($affected !== 1) {
        throw new RuntimeException('Failed to advance calendar_event_sequence');
    }

    $stmt = $pdo->query("SELECT LAST_INSERT_ID()");
    $eventId = (int)$stmt->fetchColumn();

    if ($eventId <= 0) {
        throw new RuntimeException('Invalid generated calendar event_id');
    }

    return $eventId;
}

/**
 * Self-initialize calendar event sequence.
 */
function ensure_calendar_event_sequence_row(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        INSERT INTO calendar_event_sequence (id, current_value)
        VALUES (1, 0)
        ON DUPLICATE KEY UPDATE current_value = current_value
    ");

    $stmt->execute();
}