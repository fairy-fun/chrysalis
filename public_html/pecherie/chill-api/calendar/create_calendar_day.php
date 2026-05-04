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

$parentWeekEntityId = $body['parent_week_entity_id'] ?? null;
$dayIndex = $body['day_index'] ?? null;
$dayLabel = $body['day_label'] ?? null;
$realDateId = $body['real_date_id'] ?? null;

if (!is_string($parentWeekEntityId) || trim($parentWeekEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_week_entity_id must be a non-empty string',
    ]);
}

if (!is_int($dayIndex) || $dayIndex < 1) {
    respond(400, [
        'status' => 'error',
        'error' => 'day_index must be a positive integer',
    ]);
}

if (!is_string($dayLabel) || trim($dayLabel) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'day_label must be a non-empty string',
    ]);
}

if (!is_string($realDateId) || trim($realDateId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'real_date_id must be a non-empty string',
    ]);
}

$parentWeekEntityId = trim($parentWeekEntityId);
$dayLabel = trim($dayLabel);
$realDateId = trim($realDateId);

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $day = ensure_calendar_day(
        $pdo,
        $parentWeekEntityId,
        $dayIndex,
        [
            'summary' => $dayLabel,
            'real_date_start_id' => $realDateId,
            'real_date_end_id' => $realDateId,
        ]
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'day' => $day,
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
        'error' => 'Failed to create calendar day',
        'database' => $expectedDatabase,
    ], $e);
}
