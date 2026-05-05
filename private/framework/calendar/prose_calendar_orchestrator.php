<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_prose_batch_planner.php';
require_once __DIR__ . '/calendar_subevent_service.php';

/**
 * Execute a prose → calendar batch with replay safety + diffing.
 */
function execute_calendar_batch_from_prose(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {

    $plan = generate_calendar_batch_from_prose(
        $pdo,
        $parentEventEntityId,
        $prose
    );

    if (($plan['status'] ?? null) !== 'ok') {
        throw new RuntimeException('Planner failed');
    }

    $planId = $plan['plan_id'] ?? null;
    $operations = $plan['operations'] ?? [];

    if (!is_string($planId) || $planId === '') {
        throw new RuntimeException('Missing plan_id');
    }

    $existing = load_existing_subevents($pdo, $parentEventEntityId);

    $incomingHashes = [];
    $incomingOrder = [];

    $results = [];
    $executedCount = 0;
    $idempotentCount = 0;

    foreach ($operations as $index => $op) {

        if (($op['operation'] ?? '') !== 'createCalendarSubevent') {
            continue;
        }

        $clientId = $planId . ':' . $index;
        $beatHash = $op['beat_hash'] ?? null;

        if (is_string($beatHash) && $beatHash !== '') {
            $incomingHashes[] = $beatHash;
            $incomingOrder[$beatHash] = (int)($op['order_index'] ?? $index);
        }

        $payload = [
            'parent_event_entity_id' => $op['parent_event_entity_id'] ?? null,
            'event_label' => $op['event_label'] ?? null,
            'beat_type_id' => $op['beat_type_id'] ?? null,
            'client_id' => $clientId,
        ];

        if (isset($op['beat_inference'])) {
            $payload['beat_inference'] = $op['beat_inference'];
        }

        if (is_string($beatHash) && $beatHash !== '') {
            $payload['beat_hash'] = $beatHash;
        }

        $result = create_calendar_subevent_core($pdo, $payload);

        if (!empty($result['idempotent'])) {
            $idempotentCount++;
        } else {
            $executedCount++;
        }

        $results[] = [
            'index' => $index,
            'client_id' => $clientId,
            'beat_hash' => $beatHash,
            'order_index' => $incomingOrder[$beatHash] ?? null,
            'result' => $result,
        ];
    }

    $existingHashes = array_keys($existing);
    $toDelete = array_diff($existingHashes, $incomingHashes);

    $deleteCandidates = [];

    foreach ($toDelete as $hash) {
        $deleteCandidates[] = [
            'beat_hash' => $hash,
            'entity_id' => $existing[$hash]['entity_id'],
            'event_id' => (int)$existing[$hash]['event_id'],
            'position' => (int)$existing[$hash]['position'],
            'manual_action' => [
                'type' => 'delete_subevent',
                'event_id' => (int)$existing[$hash]['event_id'],
                'layer_id' => 'calendar_layer_subevent',
            ],
        ];
    }

    $orderCandidates = [];

    foreach ($incomingOrder as $hash => $desiredPosition) {
        if (!isset($existing[$hash])) {
            continue;
        }

        $currentPosition = (int)$existing[$hash]['position'];

        if ($currentPosition !== (int)$desiredPosition) {
            $orderCandidates[] = [
                'beat_hash' => $hash,
                'entity_id' => $existing[$hash]['entity_id'],
                'event_id' => (int)$existing[$hash]['event_id'],
                'current_position' => $currentPosition,
                'desired_position' => (int)$desiredPosition,
                'manual_action' => [
                    'type' => 'reorder_subevent',
                    'event_id' => (int)$existing[$hash]['event_id'],
                    'beat_hash' => $hash,
                    'from' => $currentPosition,
                    'to' => (int)$desiredPosition,
                ],
            ];
        }
    }

    return [
        'status' => 'ok',
        'plan_id' => $planId,
        'operation_count' => count($operations),
        'executed_count' => $executedCount,
        'idempotent_count' => $idempotentCount,
        'delete_required' => count($deleteCandidates) > 0,
        'delete_candidate_count' => count($deleteCandidates),
        'delete_candidates' => $deleteCandidates,
        'order_reconciliation_required' => count($orderCandidates) > 0,
        'order_candidate_count' => count($orderCandidates),
        'order_candidates' => $orderCandidates,
        'results' => $results,
    ];
}


# =========================================================
# HELPERS
# =========================================================

function load_existing_subevents(PDO $pdo, string $parentEntityId): array {

    $stmt = $pdo->prepare("
        SELECT event_id
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE entity_id = :entity_id
        AND layer_id = 'calendar_layer_event'
        LIMIT 1
    ");
    $stmt->execute([':entity_id' => $parentEntityId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Invalid parent_event_entity_id');
    }

    $parentEventId = $row['event_id'];
    $positionColumn = 'subevent' . '_index';

    $stmt = $pdo->prepare("
        SELECT entity_id, event_id, beat_hash, {$positionColumn} AS position
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE parent_event_id = :parent_event_id
        AND layer_id = 'calendar_layer_subevent'
    ");

    $stmt->execute([':parent_event_id' => $parentEventId]);

    $map = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $hash = $row['beat_hash'] ?? null;

        if (is_string($hash) && $hash !== '') {
            $map[$hash] = [
                'entity_id' => $row['entity_id'],
                'event_id' => (int)$row['event_id'],
                'position' => (int)$row['position'],
            ];
        }
    }

    return $map;
}