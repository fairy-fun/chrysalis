<?php

declare(strict_types=1);

/**
 * Core structural ensure primitive
 */
function ensure_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    ?int $sequenceIndex,
    array $payload = []
): array {

    while (true) {

        // Resolve index
        if ($sequenceIndex !== null) {
            $candidateIndex = $sequenceIndex;
        } else {
            $candidateIndex = get_next_sequence_index(
                $pdo,
                $projectionEntityId,
                $layerId,
                $parentEventId
            );
        }

        // Try find
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
            insert_calendar_node(
                $pdo,
                $projectionEntityId,
                $layerId,
                $parentEventId,
                $candidateIndex,
                $payload
            );

            return find_calendar_node(
                $pdo,
                $projectionEntityId,
                $layerId,
                $parentEventId,
                $candidateIndex
            );

        } catch (PDOException $e) {

            // 1062 = duplicate key (MySQL/MariaDB)
            if ((int)$e->getCode() !== 23000) {
                throw $e;
            }

            // retry
            if ($sequenceIndex !== null) {
                continue; // fixed index
            }

            // append mode → recompute
            continue;
        }
    }
}

/**
 * Lookup by structural identity
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
 * Insert (ONLY place allowed to write)
 */
function insert_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    int $sequenceIndex,
    array $payload
): void {

    $stmt = $pdo->prepare("
        INSERT INTO calendar_events (
            projection_entity_id,
            parent_event_id,
            layer_id,
            sequence_index,
            entity_id,
            event_id,
            summary
        ) VALUES (
            :projection,
            :parent,
            :layer,
            :seq,
            :entity_id,
            :event_id,
            :summary
        )
    ");

    $stmt->execute([
        ':projection' => $projectionEntityId,
        ':parent' => $parentEventId,
        ':layer' => $layerId,
        ':seq' => $sequenceIndex,
        ':entity_id' => uniqid('ce_', true),
        ':event_id' => uniqid('evt_', true),
        ':summary' => $payload['summary'] ?? null
    ]);
}

/**
 * Append index
 */
function get_next_sequence_index(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId
): int {

    $stmt = $pdo->prepare("
        SELECT MAX(sequence_index) AS max_seq
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