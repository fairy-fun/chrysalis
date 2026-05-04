<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_layer_ensurers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$parentTimeEntityId = $body['parent_time_entity_id'] ?? null;
$eventLabel = $body['event_label'] ?? null;

if ($eventLabel !== null && !is_string($eventLabel)) {
    respond(400, [
        'status' => 'error',
        'error' => 'event_label must be a string when provided',
    ]);
}

$parentTimeEntityId = is_string($parentTimeEntityId) ? trim($parentTimeEntityId) : $parentTimeEntityId;
$eventLabel = is_string($eventLabel) ? trim($eventLabel) : null;

if (!is_string($parentTimeEntityId) || $parentTimeEntityId === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_time_entity_id required',
    ]);
}

$payload = [
    'summary' => $eventLabel !== null && $eventLabel !== ''
        ? $eventLabel
        : 'Event',
];

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $event = ensure_calendar_event(
        $pdo,
        $parentTimeEntityId,
        null,
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
        'error' => 'Failed to create calendar event',
        'database' => $expectedDatabase,
    ], $e);
}
