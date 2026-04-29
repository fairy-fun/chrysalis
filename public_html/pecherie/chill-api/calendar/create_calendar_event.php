<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/procedures/calendar_event_id.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_event_creator.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$summary = $body['summary'] ?? null;
$weekIndex = $body['week_index'] ?? null;
$dayIndex = $body['day_index'] ?? null;
$timeIndex = $body['time_index'] ?? null;
$eventIndex = $body['event_index'] ?? null;
$subeventIndex = $body['subevent_index'] ?? null;
$chronologyAddress = $body['chronology_address'] ?? null;
$parentEventId = $body['parent_event_id'] ?? null;

/**
 * Validation — mirror existing API style exactly
 */

if (!is_string($summary) || trim($summary) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'summary must be a non-empty string',
    ]);
}

if (!is_int($weekIndex) || $weekIndex < 1) {
    respond(400, [
        'status' => 'error',
        'error' => 'week_index must be a positive integer',
    ]);
}

if ($dayIndex !== null && (!is_int($dayIndex) || $dayIndex < 1)) {
    respond(400, [
        'status' => 'error',
        'error' => 'day_index must be null or a positive integer',
    ]);
}

if ($timeIndex !== null && (!is_int($timeIndex) || $timeIndex < 1)) {
    respond(400, [
        'status' => 'error',
        'error' => 'time_index must be null or a positive integer',
    ]);
}

if ($eventIndex !== null && (!is_int($eventIndex) || $eventIndex < 1)) {
    respond(400, [
        'status' => 'error',
        'error' => 'event_index must be null or a positive integer',
    ]);
}

if ($subeventIndex !== null && (!is_int($subeventIndex) || $subeventIndex < 1)) {
    respond(400, [
        'status' => 'error',
        'error' => 'subevent_index must be null or a positive integer',
    ]);
}

if (!is_string($chronologyAddress) || trim($chronologyAddress) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'chronology_address must be a non-empty string',
    ]);
}

if ($parentEventId !== null && (!is_int($parentEventId) || $parentEventId < 1)) {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_event_id must be null or a positive integer',
    ]);
}

$summary = trim($summary);
$chronologyAddress = trim($chronologyAddress);

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $layerId = $body['layer_id'] ?? null;

    if (!is_string($layerId) || trim($layerId) === '') {
        respond(400, [
            'status' => 'error',
            'error' => 'layer_id must be a non-empty string',
        ]);
    }

    $layerId = trim($layerId);

    $event = create_calendar_event(
        $pdo,
        $summary,
        $weekIndex,
        $dayIndex,
        $timeIndex,
        $eventIndex,
        $subeventIndex,
        $chronologyAddress,
        $parentEventId,
        $layerId
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