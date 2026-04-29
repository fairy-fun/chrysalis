<?php

declare(strict_types=1);

function create_calendar_event(
    PDO $pdo,
    string $summary,
    int $weekIndex,
    ?int $dayIndex,
    ?int $timeIndex,
    ?int $eventIndex,
    ?int $subeventIndex,
    string $chronologyAddress,
    ?int $parentEventId = null
): array {
    $summary = trim($summary);
    $chronologyAddress = trim($chronologyAddress);

    if ($summary === '') {
        throw new InvalidArgumentException('summary must be a non-empty string');
    }

    if ($weekIndex < 1) {
        throw new InvalidArgumentException('week_index must be a positive integer');
    }

    if ($chronologyAddress === '') {
        throw new InvalidArgumentException('chronology_address must be a non-empty string');
    }

    validate_calendar_indices_match_chronology_address(
        $weekIndex,
        $dayIndex,
        $timeIndex,
        $eventIndex,
        $subeventIndex,
        $chronologyAddress
    );

    $layerId = resolve_calendar_layer_id_from_chronology_address($chronologyAddress);

    $resolvedParentEventId = resolve_calendar_parent_event_id_from_chronology_address(
        $pdo,
        $chronologyAddress
    );

    if ($parentEventId !== null && $parentEventId !== $resolvedParentEventId) {
        throw new InvalidArgumentException(
            'Provided parent_event_id does not match chronology_address-derived parent_event_id'
        );
    }

    $parentEventId = $resolvedParentEventId;

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $eventId = next_calendar_event_id($pdo);
        $entityId = 'calendar_event:' . $eventId;

        $stmt = $pdo->prepare(
            "INSERT INTO sxnzlfun_chrysalis.calendar_events (
                entity_id,
                layer_id,
                event_id,
                summary,
                week_index,
                day_index,
                time_index,
                event_index,
                subevent_index,
                chronology_address,
                parent_event_id
            ) VALUES (
                :entity_id,
                :layer_id,
                :event_id,
                :summary,
                :week_index,
                :day_index,
                :time_index,
                :event_index,
                :subevent_index,
                :chronology_address,
                :parent_event_id
            )"
        );

        $stmt->execute([
            ':entity_id' => $entityId,
            ':layer_id' => $layerId,
            ':event_id' => $eventId,
            ':summary' => $summary,
            ':week_index' => $weekIndex,
            ':day_index' => $dayIndex,
            ':time_index' => $timeIndex,
            ':event_index' => $eventIndex,
            ':subevent_index' => $subeventIndex,
            ':chronology_address' => $chronologyAddress,
            ':parent_event_id' => $parentEventId,
        ]);

        $id = (int) $pdo->lastInsertId();

        if ($id < 1) {
            throw new RuntimeException('Failed to read inserted calendar_events.id');
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM sxnzlfun_chrysalis.calendar_events
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $id,
        ]);

        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($event)) {
            throw new RuntimeException('Failed to fetch created calendar event');
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $event;

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function validate_calendar_indices_match_chronology_address(
    int $weekIndex,
    ?int $dayIndex,
    ?int $timeIndex,
    ?int $eventIndex,
    ?int $subeventIndex,
    string $chronologyAddress
): void {
    $chronologyAddress = trim($chronologyAddress);

    if ($chronologyAddress === '') {
        throw new InvalidArgumentException('chronology_address must be a non-empty string');
    }

    $rawParts = explode('.', $chronologyAddress);

    foreach ($rawParts as $part) {
        if ($part === '' || !ctype_digit($part)) {
            throw new InvalidArgumentException(
                'chronology_address must contain only positive integer parts separated by dots'
            );
        }

        if ((int) $part < 1) {
            throw new InvalidArgumentException(
                'chronology_address parts must be positive integers'
            );
        }
    }

    if (count($rawParts) > 4) {
        throw new InvalidArgumentException(
            'Invalid chronology_address depth: ' . $chronologyAddress
        );
    }

    $parts = array_map('intval', $rawParts);

    $expected = [
        $weekIndex,
        $dayIndex,
        $timeIndex,
        $eventIndex,
        $subeventIndex,
    ];

    foreach ($parts as $i => $part) {
        if ($expected[$i] !== $part) {
            throw new InvalidArgumentException(
                'Calendar indices do not match chronology_address'
            );
        }
    }

    for ($i = count($parts); $i < count($expected); $i++) {
        if ($expected[$i] !== null) {
            throw new InvalidArgumentException(
                'Calendar index is populated beyond chronology_address depth'
            );
        }
    }
}

function resolve_calendar_layer_id_from_chronology_address(string $chronologyAddress): string
{
    $depth = substr_count(trim($chronologyAddress), '.') + 1;

    return match ($depth) {
        1 => 'calendar_layer_week',
        2 => 'calendar_layer_day',
        3 => 'calendar_layer_time',
        4 => 'calendar_layer_event',
        default => throw new InvalidArgumentException(
            'Invalid chronology_address depth: ' . $chronologyAddress
        ),
    };
}

function resolve_calendar_parent_event_id_from_chronology_address(
    PDO $pdo,
    string $chronologyAddress
): ?int {
    $chronologyAddress = trim($chronologyAddress);
    $parts = explode('.', $chronologyAddress);

    if (count($parts) === 1) {
        return null;
    }

    array_pop($parts);
    $parentChronologyAddress = implode('.', $parts);

    $stmt = $pdo->prepare(
        "SELECT id
         FROM sxnzlfun_chrysalis.calendar_events
         WHERE chronology_address = :chronology_address
         LIMIT 1"
    );

    $stmt->execute([
        ':chronology_address' => $parentChronologyAddress,
    ]);

    $parentId = $stmt->fetchColumn();

    if ($parentId === false) {
        throw new RuntimeException(
            'No parent calendar event found for chronology_address ' . $chronologyAddress .
            '; expected parent chronology_address ' . $parentChronologyAddress
        );
    }

    return (int) $parentId;
}