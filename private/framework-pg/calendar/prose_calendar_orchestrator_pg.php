<?php

declare(strict_types=1);

/**
 * PostgreSQL-compatible version of prose_calendar_orchestrator.php
 *
 * Changes from MySQL original:
 * - Removed hardcoded `sxnzlfun_chrysalis.` database prefix from all queries.
 *   PostgreSQL resolves tables via search_path (default: public schema).
 */

require_once __DIR__ . '/../../framework/prose/prose_subevent_segmenter.php';
require_once __DIR__ . '/../../framework/calendar/calendar_subevent_service.php';

function build_subevent_client_id_pg(
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
function resolve_parent_event_id_pg(PDO $pdo, string $parentEventEntityId): int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM calendar_events
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

    return (int)$row['id'];
}

/**
 * Persistence layer (event_id-native, idempotent)
 */
function persist_segmented_subevents_pg(
    PDO $pdo,
    int $parentEventId,
    string $parentEventEntityId,
    array $subevents
): array {

    $persisted = [];

    foreach ($subevents as $subevent) {

        $slot      = (int)($subevent['slot'] ?? 0);
        $summary   = trim((string)($subevent['summary'] ?? ''));
        $proseBody = trim((string)($subevent['prose_body'] ?? ''));

        if ($slot < 1) {
            throw new RuntimeException('Invalid subevent slot');
        }

        if ($summary === '' || $proseBody === '') {
            throw new RuntimeException('Invalid subevent payload');
        }

        // IDENTITY GUARD
        $check = $pdo->prepare("
            SELECT entity_id
            FROM calendar_events
            WHERE parent_event_id = :parent_event_id
              AND subevent_index = :slot
            LIMIT 1
        ");

        $check->execute([
            ':parent_event_id' => $parentEventId,
            ':slot'            => $slot,
        ]);

        if ($existing = $check->fetch(PDO::FETCH_ASSOC)) {
            $persisted[] = [
                'status'        => 'ok',
                'idempotent'    => true,
                'event'         => [
                    'entity_id'      => $existing['entity_id'],
                    'subevent_index' => $slot,
                ],
            ];
            continue;
        }

        // CREATE (delegates to canonical service)
        $persisted[] = create_calendar_subevent_core(
            $pdo,
            [
                'client_id'              => build_subevent_client_id_pg($parentEventEntityId, $slot),
                'parent_event_entity_id' => $parentEventEntityId,
                'subevent_index'         => $slot,
                'event_label'            => $summary,
                'beat_type_id'           => $subevent['beat_type_id'] ?? null,
                'beat_hash'              => $subevent['beat_hash'] ?? null,
                'prose_body'             => $proseBody,
            ]
        );
    }

    return $persisted;
}

function execute_calendar_batch_from_prose_pg(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {

    $parentEventEntityId = trim($parentEventEntityId);

    if ($parentEventEntityId === '') {
        throw new InvalidArgumentException('parentEventEntityId must be non-empty');
    }

    // Resolve ONCE (ENTITY → EVENT ID boundary)
    $parentEventId = resolve_parent_event_id_pg($pdo, $parentEventEntityId);

    $subevents = segment_prose_into_subevents($pdo, $prose);

    if ($subevents === []) {
        return [
            'status'                 => 'ok',
            'parent_event_entity_id' => $parentEventEntityId,
            'parent_event_id'        => $parentEventId,
            'subevent_count'         => 0,
            'persisted_count'        => 0,
            'results'                => [],
        ];
    }

    $persisted = persist_segmented_subevents_pg(
        $pdo,
        $parentEventId,
        $parentEventEntityId,
        $subevents
    );

    return [
        'status'                 => 'ok',
        'parent_event_entity_id' => $parentEventEntityId,
        'parent_event_id'        => $parentEventId,
        'subevent_count'         => count($subevents),
        'persisted_count'        => count($persisted),
        'results'                => $persisted,
    ];
}
