<?php

declare(strict_types=1);

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

    if ($parentEventId !== null && $parentEventId <= 0) {
        throw new InvalidArgumentException('parent_event_id must be positive when provided');
    }

    if ($sequenceIndex !== null && $sequenceIndex < 1) {
        throw new InvalidArgumentException('sequence_index must be positive when provided');
    }

    $payload = validate_calendar_node_payload($layerId, $payload);

    if (!isset($payload['summary']) || !is_string($payload['summary']) || trim($payload['summary']) === '') {
        throw new InvalidArgumentException('summary is required');
    }

    $attempts = 0;

    while (true) {
        $attempts++;

        if ($attempts > 3) {
            throw new RuntimeException('Failed to ensure calendar node after structural duplicate retries');
        }

        $candidateSequenceIndex = $sequenceIndex ?? get_next_calendar_sequence_index(
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
            $candidateSequenceIndex
        );

        if ($existing !== null) {
            ensure_calendar_event_entity_exists($pdo, (int) $existing['event_id']);
            return $existing;
        }

        try {
            $node = insert_calendar_node(
                $pdo,
                $projectionEntityId,
                $layerId,
                $parentEventId,
                $candidateSequenceIndex,
                $payload
            );

            return $node;

        } catch (\PDOException $e) {
            if (!is_calendar_structural_duplicate_key($e)) {
                throw $e;
            }

            if ($sequenceIndex !== null) {
                return require_calendar_node(
                    $pdo,
                    $projectionEntityId,
                    $layerId,
                    $parentEventId,
                    $candidateSequenceIndex
                );
            }

            continue;
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
        throw new RuntimeException('Required calendar node not found.');
    }

    ensure_calendar_event_entity_exists($pdo, (int) $node['event_id']);

    return $node;
}

function find_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    int $sequenceIndex
): ?array {
    $projectionEntityId = trim($projectionEntityId);
    $layerId = trim($layerId);

    if ($projectionEntityId === '') {
        throw new InvalidArgumentException('projection_entity_id must be non-empty');
    }

    if ($layerId === '') {
        throw new InvalidArgumentException('layer_id must be non-empty');
    }

    if ($parentEventId !== null && $parentEventId <= 0) {
        throw new InvalidArgumentException('parent_event_id must be positive when provided');
    }

    if ($sequenceIndex < 1) {
        throw new InvalidArgumentException('sequence_index must be positive');
    }

    $stmt = $pdo->prepare(
        '
        SELECT *
        FROM calendar_events
        WHERE projection_entity_id = :projection_entity_id
          AND layer_id = :layer_id
          AND sequence_index = :sequence_index
          AND parent_event_id_norm = IFNULL(:parent_event_id, 0)
        LIMIT 1
        '
    );

    $stmt->execute([
        ':projection_entity_id' => $projectionEntityId,
        ':layer_id' => $layerId,
        ':sequence_index' => $sequenceIndex,
        ':parent_event_id' => $parentEventId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function insert_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId,
    int $sequenceIndex,
    array $payload
): array {
    $payload = validate_calendar_node_payload($layerId, $payload);

    $startedTransaction = !$pdo->inTransaction();

    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $eventId = generate_event_id($pdo);
        $entityId = calendar_event_entity_id($eventId);

        ensure_entity_row($pdo, $entityId, 'entity_type_calendar_event');

        $stmt = $pdo->prepare(
            '
            INSERT INTO calendar_events (
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
                :projection_entity_id,
                :parent_event_id,
                :layer_id,
                :sequence_index,
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
            '
        );

        $stmt->execute([
            ':entity_id' => $entityId,
            ':projection_entity_id' => trim($projectionEntityId),
            ':parent_event_id' => $parentEventId,
            ':layer_id' => trim($layerId),
            ':sequence_index' => $sequenceIndex,
            ':event_id' => $eventId,
            ':summary' => $payload['summary'],
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

        $id = (int) $pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }

        return get_calendar_node_by_id($pdo, $id);
    } catch (\Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function validate_calendar_node_payload(string $layerId, array $payload): array
{
    $forbiddenFields = [
        'id',
        'event_id',
        'entity_id',
        'parent_event_id',
        'parent_event_id_norm',
        'projection_entity_id',
        'layer_id',
        'sequence_index',
        'week_index',
        'day_index',
        'time_index',
        'event_index',
        'chronology_address',
        'created_at',
        'updated_at',
    ];

    foreach ($forbiddenFields as $field) {
        if (array_key_exists($field, $payload)) {
            throw new InvalidArgumentException(
                'Calendar node payload may not contain structural identity field: ' . $field
            );
        }
    }

    $allowedByLayer = [
        'calendar_layer_week' => [
            'summary',
            'real_date_start_id',
            'real_date_end_id',
        ],
        'calendar_layer_day' => [
            'summary',
            'real_date_start_id',
            'real_date_end_id',
        ],
        'calendar_layer_time' => [
            'summary',
            'time_label_id',
        ],
        'calendar_layer_event' => [
            'summary',
            'event_type_id',
            'location_id',
            'domain_id',
            'class_type_id',
            'notes',
            'source_document',
        ],
    ];

    if (!array_key_exists($layerId, $allowedByLayer)) {
        throw new InvalidArgumentException('Unsupported calendar layer_id: ' . $layerId);
    }

    $allowed = array_fill_keys($allowedByLayer[$layerId], true);

    foreach ($payload as $key => $_value) {
        if (!is_string($key) || !array_key_exists($key, $allowed)) {
            throw new InvalidArgumentException(
                'Calendar node payload field is not allowed for layer ' . $layerId . ': ' . (string) $key
            );
        }
    }

    return $payload;
}

function get_next_calendar_sequence_index(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentEventId
): int {
    $stmt = $pdo->prepare(
        '
        SELECT MAX(sequence_index)
        FROM calendar_events
        WHERE projection_entity_id = :projection_entity_id
          AND layer_id = :layer_id
          AND parent_event_id_norm = IFNULL(:parent_event_id, 0)
        '
    );

    $stmt->execute([
        ':projection_entity_id' => trim($projectionEntityId),
        ':layer_id' => trim($layerId),
        ':parent_event_id' => $parentEventId,
    ]);

    $max = $stmt->fetchColumn();

    return ($max !== false && $max !== null) ? ((int) $max + 1) : 1;
}

function get_calendar_node_by_id(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        throw new InvalidArgumentException('id must be positive');
    }

    $stmt = $pdo->prepare(
        '
        SELECT *
        FROM calendar_events
        WHERE id = :id
        LIMIT 1
        '
    );

    $stmt->execute([
        ':id' => $id,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('Calendar node not found after insert.');
    }

    ensure_calendar_event_entity_exists($pdo, (int) $row['event_id']);

    return $row;
}

function ensure_calendar_event_entity_exists(PDO $pdo, int $eventId): void
{
    if ($eventId <= 0) {
        throw new InvalidArgumentException('event_id must be positive');
    }

    ensure_entity_row(
        $pdo,
        calendar_event_entity_id($eventId),
        'entity_type_calendar_event'
    );
}

function ensure_entity_row(PDO $pdo, string $entityId, string $entityTypeId): void
{
    $entityId = trim($entityId);
    $entityTypeId = trim($entityTypeId);

    if ($entityId === '') {
        throw new InvalidArgumentException('entity_id must be non-empty');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('entity_type_id must be non-empty');
    }

    $stmt = $pdo->prepare(
        '
        INSERT INTO entities (id, entity_type_id)
        VALUES (:id, :entity_type_id)
        ON DUPLICATE KEY UPDATE id = id
        '
    );

    $stmt->execute([
        ':id' => $entityId,
        ':entity_type_id' => $entityTypeId,
    ]);
}

function calendar_event_entity_id(int $eventId): string
{
    if ($eventId <= 0) {
        throw new InvalidArgumentException('event_id must be positive');
    }

    return 'calendar_event:' . $eventId;
}

function generate_event_id(PDO $pdo): int
{
    ensure_calendar_event_sequence_row($pdo);

    $affected = $pdo->exec(
        '
        UPDATE calendar_event_sequence
        SET current_value = LAST_INSERT_ID(current_value + 1)
        WHERE id = 1
        '
    );

    if ($affected !== 1) {
        throw new RuntimeException('Failed to advance calendar_event_sequence.');
    }

    $stmt = $pdo->query('SELECT LAST_INSERT_ID()');

    if (!$stmt) {
        throw new RuntimeException('Failed to read generated event_id.');
    }

    $eventId = (int) $stmt->fetchColumn();

    if ($eventId <= 0) {
        throw new RuntimeException('Invalid generated event_id.');
    }

    return $eventId;
}

function ensure_calendar_event_sequence_row(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        '
        INSERT INTO calendar_event_sequence (id, current_value)
        VALUES (1, 0)
        ON DUPLICATE KEY UPDATE current_value = current_value
        '
    );

    $stmt->execute();
}

function is_calendar_structural_duplicate_key(\PDOException $e): bool
{
    return isset($e->errorInfo[1], $e->errorInfo[2])
        && (int) $e->errorInfo[1] === 1062
        && str_contains((string) $e->errorInfo[2], 'ux_calendar_structural_identity');
}