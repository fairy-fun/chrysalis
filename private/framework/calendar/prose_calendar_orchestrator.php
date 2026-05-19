<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_subevent_segmenter.php';
require_once __DIR__ . '/calendar_subevent_service.php';

function build_subevent_client_id(
    string $parentEventEntityId,
    int $slot
): string {

    $parentEventEntityId = trim($parentEventEntityId);

    if ($parentEventEntityId === '') {
        throw new InvalidArgumentException('parentEventEntityId must be non-empty');
    }

    if ($slot < 1) {
        throw new InvalidArgumentException('slot must be >= 1');
    }

    return sprintf('%s:slot:%d', $parentEventEntityId, $slot);
}

/**
 * Resolve parent once (ENTITY → EVENT ID boundary)
 */
function resolve_parent_event_id(PDO $pdo, string $parentEventEntityId): int
{
    $stmt = $pdo->prepare("
        SELECT event_id
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $parentEventEntityId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Invalid parent event reference');
    }

    return (int)$row['event_id'];
}

/**
 * Persistence layer (event_id-native, idempotent)
 */
function persist_segmented_subevents(
    PDO $pdo,
    int $parentEventId,
    string $parentEventEntityId,
    array $subevents
): array {

    $persisted = [];

    foreach ($subevents as $subevent) {

        $slot = (int)($subevent['slot'] ?? 0);
        $summary = trim((string)($subevent['summary'] ?? ''));
        $proseBody = trim((string)($subevent['prose_body'] ?? ''));

        if ($slot < 1) {
            throw new RuntimeException('Invalid subevent slot');
        }

        if ($summary === '' || $proseBody === '') {
            throw new RuntimeException('Invalid subevent payload');
        }

        /**
         * IDENTITY GUARD (fully indexable, no subquery)
         */
        $check = $pdo->prepare("
            SELECT entity_id
            FROM sxnzlfun_chrysalis.calendar_events
            WHERE parent_event_id = :parent_event_id
              AND subevent_index = :slot
            LIMIT 1
        ");

        $check->execute([
            ':parent_event_id' => $parentEventId,
            ':slot' => $slot,
        ]);

        if ($existing = $check->fetch(PDO::FETCH_ASSOC)) {
            $persisted[] = [
                'status' => 'ok',
                'idempotent' => true,
                'event' => [
                    'entity_id' => $existing['entity_id'],
                    'subevent_index' => $slot
                ]
            ];
            continue;
        }

        /**
         * CREATE (delegates to canonical service)
         */
        $persisted[] = create_calendar_subevent_core(
            $pdo,
            [
                'client_id' => build_subevent_client_id($parentEventEntityId, $slot),
                'parent_event_entity_id' => $parentEventEntityId,
                'subevent_index' => $slot,
                'event_label' => $summary,
                'prose_body' => $proseBody,
            ]
        );
    }

    return $persisted;
}

function execute_calendar_batch_from_prose(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {

    $parentEventEntityId = trim($parentEventEntityId);

    if ($parentEventEntityId === '') {
        throw new InvalidArgumentException('parentEventEntityId must be non-empty');
    }

    /**
     * Resolve ONCE (important boundary shift)
     */
    $parentEventId = resolve_parent_event_id($pdo, $parentEventEntityId);

    $subevents = segment_prose_into_subevents($prose);

    if ($subevents === []) {
        return [
            'status' => 'ok',
            'parent_event_entity_id' => $parentEventEntityId,
            'parent_event_id' => $parentEventId,
            'subevent_count' => 0,
            'persisted_count' => 0,
            'results' => [],
        ];
    }

    $persisted = persist_segmented_subevents(
        $pdo,
        $parentEventId,
        $parentEventEntityId,
        $subevents
    );

    return [
        'status' => 'ok',
        'parent_event_entity_id' => $parentEventEntityId,
        'parent_event_id' => $parentEventId,
        'subevent_count' => count($subevents),
        'persisted_count' => count($persisted),
        'results' => $persisted,
    ];
}