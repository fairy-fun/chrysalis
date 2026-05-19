<?php

declare(strict_types=1);

/**
 * Resolve canonical prose attachment state for a calendar event graph.
 *
 * Authority:
 *
 * prose_projections.target_entity_id
 * → prose_projections.published_prose_draft_id
 * → prose_drafts.prose_body
 *
 * calendar_events.prose_body is intentionally not used as authority.
 *
 * Live hierarchy model:
 *
 * calendar_events.parent_event_id
 */
function resolve_event_graph_prose(
    PDO $pdo,
    int|string $eventIdentity
): array {

    $event = resolve_calendar_event_for_prose_graph(
        $pdo,
        $eventIdentity
    );

    if ($event === null) {
        return [
            'status' => 'event_not_found',
            'entity_id' => is_string($eventIdentity) ? $eventIdentity : null,
            'event_id' => is_int($eventIdentity) ? $eventIdentity : null,
            'prose_state' => 'none',
            'has_direct_prose' => false,
            'has_child_event_prose' => false,
            'prose_targets' => [],
            'direct_prose' => [],
            'child_event_prose' => [],
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.entity_id,
            e.parent_event_id,
            e.layer_id,
            e.summary,
            pp.id AS prose_projection_id,
            pp.published_prose_draft_id,
            pp.role_id,
            pp.projection_order,
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pd.summary AS prose_summary,
            pd.prose_body
        FROM calendar_events e
        LEFT JOIN prose_projections pp
            ON pp.target_entity_id = e.entity_id
        LEFT JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
        WHERE e.id = :event_id
           OR e.parent_event_id = :event_id
        ORDER BY
            CASE
                WHEN e.id = :event_id THEN 0
                ELSE 1
            END ASC,
            e.subevent_index ASC,
            e.sequence_index ASC,
            e.id ASC,
            pp.id ASC
    ");

    $stmt->execute([
        ':event_id' => (int)$event['id'],
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $directProse = [];
    $childEventProse = [];
    $proseTargets = [];

    foreach ($rows as $row) {
        if (empty($row['prose_projection_id'])) {
            continue;
        }

        if (empty($row['published_prose_draft_id']) || empty($row['prose_draft_id'])) {
            continue;
        }

        if (trim((string)($row['prose_body'] ?? '')) === '') {
            continue;
        }

        $entry = [
            'event' => [
                'id' => (int)$row['id'],
                'entity_id' => (string)$row['entity_id'],
                'parent_event_id' => $row['parent_event_id'],
                'layer_id' => (string)$row['layer_id'],
                'summary' => $row['summary'],
            ],
            'published_prose' => [
                'prose_projection_id' => (int)$row['prose_projection_id'],
                'published_prose_draft_id' => (int)$row['published_prose_draft_id'],
                'prose_draft_id' => (int)$row['prose_draft_id'],
                'prose_entity_id' => $row['prose_entity_id'],
                'title' => $row['title'],
                'summary' => $row['prose_summary'],
                'prose_body' => $row['prose_body'],
                'role_id' => $row['role_id'],
                'projection_order' => $row['projection_order'],
            ],
        ];

        $proseTargets[] = (string)$row['entity_id'];

        if ((int)$row['id'] === (int)$event['id']) {
            $directProse[] = $entry;
        } else {
            $childEventProse[] = $entry;
        }
    }

    $hasDirectProse = $directProse !== [];
    $hasChildEventProse = $childEventProse !== [];

    if ($hasDirectProse && $hasChildEventProse) {
        $proseState = 'mixed';
    } elseif ($hasDirectProse) {
        $proseState = 'direct';
    } elseif ($hasChildEventProse) {
        $proseState = 'child_only';
    } else {
        $proseState = 'none';
    }

    return [
        'status' => 'ok',
        'event' => $event,
        'entity_id' => $event['entity_id'],
        'event_id' => (int)$event['id'],
        'prose_state' => $proseState,
        'has_direct_prose' => $hasDirectProse,
        'has_child_event_prose' => $hasChildEventProse,
        'prose_targets' => array_values(array_unique($proseTargets)),
        'direct_prose' => $directProse,
        'child_event_prose' => $childEventProse,
    ];
}

function resolve_calendar_event_for_prose_graph(
    PDO $pdo,
    int|string $eventIdentity
): ?array {

    if (is_int($eventIdentity) || ctype_digit((string)$eventIdentity)) {
        $stmt = $pdo->prepare("
            SELECT
                id,
                entity_id,
                parent_event_id,
                layer_id,
                summary
            FROM calendar_events
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => (int)$eventIdentity,
        ]);
    } else {
        $entityId = trim((string)$eventIdentity);

        if ($entityId === '') {
            throw new InvalidArgumentException('event identity must be non-empty');
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                entity_id,
                parent_event_id,
                layer_id,
                summary
            FROM calendar_events
            WHERE entity_id = :entity_id
            LIMIT 1
        ");

        $stmt->execute([
            ':entity_id' => $entityId,
        ]);
    }

    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($event) ? $event : null;
}
