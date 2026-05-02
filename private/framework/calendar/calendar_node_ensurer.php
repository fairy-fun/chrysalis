<?php

declare(strict_types=1);

/**
 * Core structural ensure primitive
 *
 * RULES:
 * - Only place allowed to INSERT into calendar_events
 * - Idempotent via structural identity
 * - Safe under concurrency
 * - First write wins (no updates)
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

        // Determine candidate index
        $candidateIndex = ($sequenceIndex !== null)
            ? $sequenceIndex
            : get_next_sequence_index($pdo, $projectionEntityId, $layerId, $parentEventId);

        // Try find first (fast path)
        $existing = find_calendar_node(
            $pdo,
            $projectionEntityId,
            $layerId,
            $parentEventId,
            $candidateIndex
        );

        if ($existing !== null) {
            return $existing;
        }

        try {
            $row = insert_calendar_node(
                $pdo,
                $projectionEntityId,
                $layerId,
                $parentEventId,
                $candidateIndex,
                $payload
            );

            return $row;

        } catch (PDOException $e) {

            if (!is_duplicate_key($e)) {
                throw $e;
            }

            // Another writer won

            if ($sequenceIndex !== null) {
                // fixed index → retry same
                continue;
            }

            // append mode → recompute index
            continue;
        }
    }
}

/**
 * Structural lookup
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
        ':seq' => $sequenceIndex
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Insert node (ONLY write path)
 */
function insert_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    int $sequenceIndex,
    array $payload
): array {

    // Generate required IDs
    $entityId = generate_entity_id();
    $eventId  = generate_event_id($pdo);

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
        ':summary' => $payload['summary']
    ]);

    // Return the inserted row directly
    $id = (int)$pdo->lastInsertId();

    return get_calendar_node_by_id($pdo, $id);
}

/**
 * Append index (safe under retry)
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
        ':parent' => $parentEventId
    ]);

    $max = $stmt->fetchColumn();

    return $max ? ((int)$max + 1) : 1;
}

/**
 * Fetch by primary key
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
        throw new RuntimeException('Inserted node not found');
    }

    return $row;
}

/**
 * Proper duplicate detection (MySQL/MariaDB)
 */
function is_duplicate_key(PDOException $e): bool
{
    // SQLSTATE 23000 = integrity constraint violation
    // driver-specific code 1062 = duplicate entry
    return $e->getCode() === '23000'
        || (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062);
}

/**
 * Generate entity_id (string, external identity)
 */
function generate_entity_id(): string
{
    return 'ce_' . bin2hex(random_bytes(8));
}

/**
 * Generate event_id (BIGINT, must be unique)
 *
 * NOTE: This assumes you have a helper or sequence.
 * Replace with your real generator if needed.
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

function ensure_calendar_event_sequence_row(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        INSERT INTO calendar_event_sequence (id, current_value)
        VALUES (1, 0)
        ON DUPLICATE KEY UPDATE current_value = current_value
    ");

    $stmt->execute();
}