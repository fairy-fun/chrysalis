<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/calendar/resolve_calendar_projection.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = $_QUERY_BODY ?? [];
$projectionId = $body['calendar_projection_id'] ?? null;

if (!is_int($projectionId) && !(is_string($projectionId) && ctype_digit($projectionId))) {
    respond(400, [
        'status' => 'error',
        'error' => 'calendar_projection_id must be a positive integer',
    ]);
}

$projectionId = (int)$projectionId;

if ($projectionId <= 0) {
    respond(400, [
        'status' => 'error',
        'error' => 'Invalid calendar_projection_id',
    ]);
}

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $tree = resolve_calendar_projection($pdo, $projectionId);

    if (empty($tree)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Projection not found or empty',
            'database' => $expectedDatabase,
        ]);
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'calendar_projection_id' => $projectionId,
        'tree' => $tree,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to resolve calendar projection',
        'database' => $expectedDatabase,
    ], $e);
}