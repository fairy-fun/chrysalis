<?php

declare(strict_types=1);

/**
 * Calendar event ontology linkage guards.
 *
 * Semantic narrative surfaces are freeform text:
 * - calendar_events.summary
 * - calendar_events.notes
 *
 * Ontology linkage fields are canonical references only:
 * - calendar_events.beat_type_id  -> cvt_calendar_beat_type.id
 * - calendar_events.class_type_id -> calendar_class_type_classvals.id
 *
 * Beat classsets are taxonomy/grouping structures only. Events store concrete
 * beat type ids, never calendar_beat_classsets.id values.
 */
function assert_calendar_node_ontology_linkage_fields(
    PDO $pdo,
    string $layerId,
    array $payload
): void {

    $layerId = trim($layerId);

    if (array_key_exists('class_type_id', $payload)) {
        assert_calendar_class_type_id($pdo, $payload['class_type_id']);
    }

    if (array_key_exists('beat_type_id', $payload)) {
        assert_calendar_beat_type_id($pdo, $payload['beat_type_id']);
    }
}

function assert_calendar_class_type_id(PDO $pdo, mixed $value): void
{
    if ($value === null) {
        return;
    }

    $id = trim((string)$value);

    if ($id === '') {
        throw new InvalidArgumentException(
            'calendar_events.class_type_id cannot be an empty string'
        );
    }

    $stmt = $pdo->prepare(""
        . "SELECT id\n"
        . "FROM sxnzlfun_chrysalis.calendar_class_type_classvals\n"
        . "WHERE id = :id\n"
        . "LIMIT 1"
    );

    $stmt->execute([
        ':id' => $id,
    ]);

    $found = $stmt->fetchColumn();

    if (!is_string($found) || trim($found) === '') {
        throw new InvalidArgumentException(
            'calendar_events.class_type_id must resolve to calendar_class_type_classvals.id: ' . $id
        );
    }
}

function assert_calendar_beat_type_id(PDO $pdo, mixed $value): void
{
    if ($value === null) {
        return;
    }

    $id = trim((string)$value);

    if ($id === '') {
        throw new InvalidArgumentException(
            'calendar_events.beat_type_id cannot be an empty string'
        );
    }

    $classsetStmt = $pdo->prepare(""
        . "SELECT id\n"
        . "FROM sxnzlfun_chrysalis.calendar_beat_classsets\n"
        . "WHERE id = :id\n"
        . "LIMIT 1"
    );

    $classsetStmt->execute([
        ':id' => $id,
    ]);

    $classsetId = $classsetStmt->fetchColumn();

    if (is_string($classsetId) && trim($classsetId) !== '') {
        throw new InvalidArgumentException(
            'calendar_events.beat_type_id must store a concrete cvt_calendar_beat_type.id, not a calendar_beat_classsets.id: ' . $id
        );
    }

    $stmt = $pdo->prepare(""
        . "SELECT id\n"
        . "FROM sxnzlfun_chrysalis.cvt_calendar_beat_type\n"
        . "WHERE id = :id\n"
        . "LIMIT 1"
    );

    $stmt->execute([
        ':id' => $id,
    ]);

    $found = $stmt->fetchColumn();

    if (!is_string($found) || trim($found) === '') {
        throw new InvalidArgumentException(
            'calendar_events.beat_type_id must resolve to cvt_calendar_beat_type.id: ' . $id
        );
    }
}
