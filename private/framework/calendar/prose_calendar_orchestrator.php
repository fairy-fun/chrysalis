<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_prose_batch_planner.php';

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

        $result = execute_create_calendar_subevent($pdo, $payload);

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

/**
 * Internal execution wrapper (no HTTP).
 * Mirrors create_calendar_subevent.php logic.
 */
function execute_create_calendar_subevent(PDO $pdo, array $payload): array
{
    $clientId = $payload['client_id'] ?? null;

    /**
     * Early idempotency check
     */
    if ($clientId !== null && $clientId !== '') {

        $stmt = $pdo->prepare("
            SELECT entity_id
            FROM sxnzlfun_chrysalis.calendar_events
            WHERE client_id = :client_id
            LIMIT 1
        ");

        $stmt->execute([
            ':client_id' => $clientId,
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return [
                'status' => 'ok',
                'idempotent' => true,
                'event' => [
                    'entity_id' => $existing['entity_id'],
                ],
            ];
        }
    }

    /**
     * Call domain logic directly
     * (bypasses HTTP, uses same underlying ensurer)
     */
    try {

        $result = ensure_calendar_subevent(
            $pdo,
            $payload['parent_event_entity_id'],
            null,
            array_filter([
                'summary' => $payload['event_label'] ?? null,
                'beat_type_id' => $payload['beat_type_id'] ?? null,
                'client_id' => $clientId,
            ])
        );

        return [
            'status' => 'ok',
            'event' => $result,
        ];

    } catch (PDOException $e) {

        $info = $e->errorInfo ?? null;

        $isClientDup =
            is_array($info) &&
            (int)($info[1] ?? 0) === 1062 &&
            str_contains((string)($info[2] ?? ''), 'uq_calendar_events_client_id');

        if ($isClientDup && $clientId) {

            $stmt = $pdo->prepare("
                SELECT entity_id
                FROM sxnzlfun_chrysalis.calendar_events
                WHERE client_id = :client_id
                LIMIT 1
            ");

            $stmt->execute([
                ':client_id' => $clientId,
            ]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                return [
                    'status' => 'ok',
                    'idempotent' => true,
                    'event' => [
                        'entity_id' => $existing['entity_id'],
                    ],
                ];
            }
        }

        throw $e;
    }
}