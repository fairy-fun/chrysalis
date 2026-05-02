<?php

declare(strict_types=1);

/**
 * Calendar Event Creator (Structural Model)
 *
 * This file MUST NOT perform direct inserts.
 * All writes go through ensure_calendar_node().
 */

require_once __DIR__ . '/calendar_node_ensurer.php';

/**
 * Create or ensure a calendar event under a TIME node.
 *
 * @param PDO $pdo
 * @param string $parentTimeEntityId
 * @param int|null $sequenceIndex  If null → append mode
 * @param string|null $eventLabel
 *
 * @return array
 */
function create_calendar_event(
    PDO $pdo,
    string $parentTimeEntityId,
    ?int $sequenceIndex = null,
    ?string $eventLabel = null
): array {

    $parentTime = resolve_parent_time_minimal($pdo, $parentTimeEntityId);

    $projectionEntityId = $parentTime['projection_entity_id'];
    $parentEventId = (int) $parentTime['id'];

    return ensure_calendar_node(
        $pdo,
        $projectionEntityId,
        'calendar_layer_event',
        $parentEventId,
        $sequenceIndex,
        [
            'summary' => $eventLabel ?? build_default_event_label($sequenceIndex)
        ]
    );
}

/**
 * Minimal parent resolver (STRUCTURAL ONLY)
 *
 * Validates:
 * - node exists
 * - node is TIME layer
 * - projection is resolvable
 */
function resolve_parent_time_minimal(PDO $pdo, string $entityId): array
{
    $stmt = $pdo->prepare("
        SELECT
            ce.id,
            ce.layer_id,
            COALESCE(ce.projection_entity_id, cepm.projection_entity_id) AS projection_entity_id
        FROM calendar_events ce
        LEFT JOIN calendar_event_projection_membership cepm
            ON cepm.calendar_event_id = ce.id
        WHERE ce.entity_id = :entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $entityId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Parent time node not found');
    }

    if ($row['layer_id'] !== 'calendar_layer_time') {
        throw new RuntimeException('Parent must be a TIME layer node');
    }

    if (empty($row['projection_entity_id'])) {
        throw new RuntimeException('Missing projection_entity_id for parent time node');
    }

    return $row;
}

/**
 * Default label generator
 *
 * Safe for append mode (sequenceIndex may be null)
 */
function build_default_event_label(?int $sequenceIndex): string
{
    if ($sequenceIndex === null) {
        return 'Event';
    }

    return 'Event ' . $sequenceIndex;
}