<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$parentEventEntityId = $_GET['parent_event_entity_id'] ?? null;

if (!is_string($parentEventEntityId) || trim($parentEventEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_event_entity_id must be a non-empty string',
    ]);
}

$parentEventEntityId = trim($parentEventEntityId);

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $parent = $pdo->prepare("
        SELECT
            ce.`id` AS _internal_id,
            ce.entity_id,
            ce.event_id,
            ce.parent_event_id,
            ce.layer_id,
            ce.sequence_index,
            ce.summary,
            ce.created_at,
            ce.updated_at,
            e.entity_type_id
        FROM sxnzlfun_chrysalis.entities e
        INNER JOIN sxnzlfun_chrysalis.calendar_events ce
            ON ce.entity_id = e.id
        WHERE e.id = :entity_id
        LIMIT 1
    ");

    $parent->execute([
        ':entity_id' => $parentEventEntityId,
    ]);

    $event = $parent->fetch(PDO::FETCH_ASSOC);

    if (!is_array($event)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Parent event entity not found or not a calendar node',
            'database' => $expectedDatabase,
        ]);
    }

    if ($event['entity_type_id'] !== 'entity_type_calendar_event') {
        respond(409, [
            'status' => 'error',
            'error' => 'Invalid parent entity_type_id; expected entity_type_calendar_event, got: ' . $event['entity_type_id'],
            'database' => $expectedDatabase,
        ]);
    }

    if ($event['layer_id'] !== 'calendar_layer_event') {
        respond(409, [
            'status' => 'error',
            'error' => 'Invalid parent layer_id; expected calendar_layer_event, got: ' . $event['layer_id'],
            'database' => $expectedDatabase,
        ]);
    }

    $stmt = $pdo->prepare("
        SELECT
            ce.entity_id,
            ce.event_id,
            ce.parent_event_id,
            ce.layer_id,
            ce.sequence_index,
            ce.summary,
            ce.created_at,
            ce.updated_at,
            e.entity_type_id
        FROM sxnzlfun_chrysalis.calendar_events ce
        INNER JOIN sxnzlfun_chrysalis.entities e
            ON e.id = ce.entity_id
        WHERE ce.parent_event_id = :parent_event_id
          AND ce.layer_id = 'calendar_layer_event'
          AND e.entity_type_id = 'entity_type_calendar_event'
        ORDER BY ce.sequence_index ASC, ce.event_id ASC
    ");

    $stmt->execute([
        ':parent_event_id' => (int)$event['_internal_id'],
    ]);

    $subevents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    unset($event['_internal_id']);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'event' => $event,
        'subevents' => $subevents,
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'error' => 'Failed to fetch calendar events for event',
        'database' => $expectedDatabase,
    ], $e);
}