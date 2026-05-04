<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_prose_batch_planner.php';
require_once __DIR__ . '/calendar_subevent_service.php';

/**
 * Execute a prose → calendar batch with replay safety.
 */
function execute_calendar_batch_from_prose(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {

    $plan = generate_calendar_batch_from_prose(
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

    $results = [];
    $executedCount = 0;
    $idempotentCount = 0;

    foreach ($operations as $index => $op) {

        $clientId = $planId . ':' . $index;

        if (($op['operation'] ?? '') !== 'createCalendarSubevent') {
            continue;
        }

        $payload = [
            'parent_event_entity_id' => $op['parent_event_entity_id'] ?? null,
            'event_label' => $op['event_label'] ?? null,
            'beat_type_id' => $op['beat_type_id'] ?? null,
            'client_id' => $clientId,
        ];

        // Optional passthrough
        if (isset($op['beat_inference'])) {
            $payload['beat_inference'] = $op['beat_inference'];
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
            'result' => $result,
        ];
    }

    return [
        'status' => 'ok',
        'plan_id' => $planId,
        'operation_count' => count($operations),
        'executed_count' => $executedCount,
        'idempotent_count' => $idempotentCount,
        'results' => $results,
    ];
}

