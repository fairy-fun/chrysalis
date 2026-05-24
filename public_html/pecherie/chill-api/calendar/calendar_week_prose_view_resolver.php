<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_week_prose_view_service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$projectionId = isset($_GET['projection_id'])
    ? (int)$_GET['projection_id']
    : 0;

$weekIndex = isset($_GET['week_index'])
    ? (int)$_GET['week_index']
    : 0;

$proseMode = normalize_calendar_week_prose_mode(
    isset($_GET['prose_mode'])
        ? (string)$_GET['prose_mode']
        : 'export'
);

if ($projectionId <= 0) {
    respond(400, [
        'status' => 'error',
        'error' => 'projection_id must be a positive integer',
    ]);
}

if ($weekIndex <= 0) {
    respond(400, [
        'status' => 'error',
        'error' => 'week_index must be a positive integer',
    ]);
}

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {

    $weekTree = resolve_calendar_week_prose_view(
        $pdo,
        $projectionId,
        $weekIndex,
        $proseMode
    );

    if ($weekTree === null) {
        respond(404, [
            'status' => 'error',
            'error' => 'Week not found',
            'database' => $expectedDatabase,
        ]);
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,

        'canonical' => [
            'projection_id' => $projectionId,
            'week_index' => $weekIndex,
            'prose_mode' => $proseMode,
        ],

        'data' => $weekTree,
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to resolve week prose view',
        'database' => $expectedDatabase,
    ], $e);
}
