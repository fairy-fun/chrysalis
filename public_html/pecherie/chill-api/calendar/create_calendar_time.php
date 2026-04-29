<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_time_creator.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$parentDayEntityId = $body['parent_day_entity_id'] ?? null;
$timeIndex = $body['time_index'] ?? null;
$timeLabel = $body['time_label'] ?? null;

if (!is_string($parentDayEntityId) || trim($parentDayEntityId) === '') {
    respond(400, ['status' => 'error', 'error' => 'parent_day_entity_id required']);
}

if (!is_int($timeIndex) || $timeIndex < 1) {
    respond(400, ['status' => 'error', 'error' => 'time_index must be positive integer']);
}

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $time = create_calendar_time(
        $pdo,
        $parentDayEntityId,
        $timeIndex,
        $timeLabel
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'time' => $time,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to create calendar time',
        'database' => $expectedDatabase,
    ], $e);
}