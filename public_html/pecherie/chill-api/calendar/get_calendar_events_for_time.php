<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$parentTimeEntityId = $_GET['parent_time_entity_id'] ?? null;

if (!is_string($parentTimeEntityId) || trim($parentTimeEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_time_entity_id must be a non-empty string',
    ]);
}

$parentTimeEntityId = trim($parentTimeEntityId);

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $parent = $pdo->prepare("
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
        FROM sxnzlfun_chrysalis.entities e
        INNER JOIN sxnzlfun_chrysalis.calendar_events ce
            ON ce.entity_id = e.id
        WHERE e.id = :entity_id
        LIMIT 1
    ");

    $parent->execute([
        ':entity_id' => $parentTimeEntityId,
    ]);

    $time = $parent->fetch(PDO::FETCH_ASSOC);

    if (!is_array($time)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Parent time entity not found or not a calendar node',
            'database' => $expectedDatabase,
        ]);
    }

    if ($time['entity_type_id'] !== 'entity_type_calendar_time') {
        respond(409, [
            'status' => 'error',
            'error' => 'Invalid parent entity_type_id; expected entity_type_calendar_time, got: ' . $time['entity_type_id'],
            'database' => $expectedDatabase,
        ]);
    }

    if ($time['layer_id'] !== 'calendar_layer_time') {
        respond(409, [
            'status' => 'error',
            'error' => 'Invalid parent layer_id; expected calendar_layer_time, got: ' . $time['layer_id'],
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
        ':parent_event_id' => (int)$time['event_id'],
    ]);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'time' => $time,
        'events' => $events,
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'error' => 'Failed to fetch calendar events for time',
        'database' => $expectedDatabase,
    ], $e);
}