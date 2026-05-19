<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_subevent_service.php';
require_once __DIR__ . '/../prose/prose_subevent_segmenter.php';

function reconcile_calendar_subevents(
    PDO $pdo,
    array $parentEvent,
    string $prose,
    array $options = []
): array {

    $parentEntityId = trim((string)($parentEvent['entity_id'] ?? ''));

    if ($parentEntityId === '') {
        throw new InvalidArgumentException('Missing parent event entity_id');
    }

    $existingSubevents = load_existing_calendar_subevents(
        $pdo,
        $parentEntityId
    );

    $segments = segment_prose_into_subevents(
        $pdo,
        $prose
    );

    $results = [];
    $matchedIndexes = [];
    $position = 0;

    foreach ($segments as $segment) {
        $position++;

        $existing = $existingSubevents[$position] ?? null;

        if ($existing !== null) {
            $matchedIndexes[$position] = true;

            $results[] = reconcile_existing_calendar_subevent(
                $pdo,
                $existing,
                $segment
            );

            continue;
        }

        $results[] = create_reconciled_calendar_subevent(
            $pdo,
            $parentEvent,
            $segment,
            $position,
            $options
        );
    }

    $orphaned = [];

    foreach ($existingSubevents as $subeventIndex => $row) {
        if (!isset($matchedIndexes[$subeventIndex])) {
            $orphaned[] = $row;
        }
    }

    return [
        'status' => 'ok',

        'parent_event' => [
            'entity_id' => $parentEntityId,
            'projection_id' => $parentEvent['projection_id'] ?? null,
        ],

        'summary' => [
            'existing_count' => count($existingSubevents),
            'desired_count' => count($segments),
            'reconciled_count' => count($results),
            'orphaned_count' => count($orphaned),
        ],

        'subevents' => array_values($results),
        'orphaned_subevents' => array_values($orphaned),
    ];
}

function load_existing_calendar_subevents(
    PDO $pdo,
    string $parentEntityId
): array {

    $sql = "
        SELECT
            ce.id,
            ce.entity_id,
            ce.event_id,
            ce.parent_event_id,
            ce.layer_id,
            ce.summary,
            ce.prose_body,
            ce.subevent_index,
            ce.sequence_index,
            ce.chronology_address,
            ce.beat_type_id,
            ce.beat_hash,
            ce.projection_id,
            ce.created_at,
            ce.updated_at

        FROM calendar_events ce

        INNER JOIN calendar_events parent
            ON parent.event_id = ce.parent_event_id

        WHERE parent.entity_id = :parent_entity_id
          AND parent.layer_id = 'calendar_layer_event'
          AND ce.layer_id = 'calendar_layer_subevent'

        ORDER BY
            ce.subevent_index ASC,
            ce.sequence_index ASC,
            ce.id ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':parent_entity_id' => $parentEntityId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($rows)) {
        return [];
    }

    $indexed = [];

    foreach ($rows as $row) {
        $subeventIndex = isset($row['subevent_index'])
            ? (int)$row['subevent_index']
            : null;

        if ($subeventIndex === null || $subeventIndex < 1) {
            continue;
        }

        if (isset($indexed[$subeventIndex])) {
            throw new RuntimeException(
                'Duplicate subevent_index detected for parent event'
            );
        }

        $indexed[$subeventIndex] = $row;
    }

    ksort($indexed);

    return $indexed;
}

function reconcile_existing_calendar_subevent(
    PDO $pdo,
    array $existing,
    array $segment
): array {

    $updated = update_calendar_subevent_payload(
        $pdo,
        (int)$existing['id'],
        [
            'summary' => $segment['summary'] ?? null,
            'prose_body' => $segment['prose_body'] ?? null,
            'beat_type_id' => $segment['beat_type_id'] ?? null,
            'beat_hash' => $segment['beat_hash'] ?? null,
            'subevent_index' => (int)$existing['subevent_index'],
        ]
    );

    return [
        'status' => 'updated',

        'event' => [
            'entity_id' => $updated['entity_id'],
            'event_id' => $updated['event_id'],
            'chronology_address' => $updated['chronology_address'],
            'subevent_index' => $updated['subevent_index'],
            'sequence_index' => $updated['sequence_index'],
        ],
    ];
}

function create_reconciled_calendar_subevent(
    PDO $pdo,
    array $parentEvent,
    array $segment,
    int $subeventIndex,
    array $options = []
): array {

    $payload = [
        'parent_projection_id' => $parentEvent['projection_id'] ?? null,
        'parent_event_entity_id' => $parentEvent['entity_id'],
        'event_label' => $segment['summary'] ?? 'Subevent',
        'beat_type_id' => $segment['beat_type_id'] ?? null,
        'beat_hash' => $segment['beat_hash'] ?? null,
        'prose_body' => $segment['prose_body'] ?? null,
        'subevent_index' => $subeventIndex,
        'source_document' => $options['source_document'] ?? null,
        'client_id' => build_reconciled_subevent_client_id(
            $parentEvent,
            $subeventIndex,
            $options
        ),
    ];

    $result = create_calendar_subevent_core(
        $pdo,
        $payload
    );

    return [
        'status' => 'created',
        'event' => $result['event'] ?? null,
    ];
}

function build_reconciled_subevent_client_id(
    array $parentEvent,
    int $subeventIndex,
    array $options = []
): ?string {

    $prefix = trim((string)($options['client_id_prefix'] ?? ''));

    if ($prefix === '') {
        return null;
    }

    $parentEntityId = trim((string)($parentEvent['entity_id'] ?? ''));

    if ($parentEntityId === '') {
        return null;
    }

    return implode(':', [
        $prefix,
        'subevent',
        $parentEntityId,
        (string)$subeventIndex,
    ]);
}