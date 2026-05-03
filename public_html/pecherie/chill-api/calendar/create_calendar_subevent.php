<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_event_semantic_creator.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$parentEventEntityId = $body['parent_event_entity_id'] ?? null;
$eventLabel = $body['event_label'] ?? null;

if ($eventLabel !== null && !is_string($eventLabel)) {
    respond(400, [
        'status' => 'error',
        'error' => 'event_label must be a string when provided',
    ]);
}

$parentEventEntityId = is_string($parentEventEntityId) ? trim($parentEventEntityId) : $parentEventEntityId;
$eventLabel = is_string($eventLabel) ? trim($eventLabel) : null;

if (!is_string($parentEventEntityId) || $parentEventEntityId === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_event_entity_id required',
    ]);
}

$payload = [
    'summary' => $eventLabel !== null && $eventLabel !== ''
        ? $eventLabel
        : 'Subevent',
];

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $event = create_calendar_event_under_event_entity(
        $pdo,
        $parentEventEntityId,
        $payload
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'event' => $event,
    ]);

} catch (InvalidArgumentException $e) {

    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (RuntimeException $e) {

    respond(409, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'error' => 'Failed to create calendar subevent',
        'database' => $expectedDatabase,
    ], $e);
}