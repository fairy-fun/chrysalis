<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_subevent_service.php';
require_once __DIR__ . '/../../../../private/framework/calendar/prose_calendar_orchestrator.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$parentEventEntityId = $body['parent_event_entity_id'] ?? null;
$prose = $body['prose'] ?? null;

if (!is_string($parentEventEntityId) || trim($parentEventEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_event_entity_id required',
    ]);
}

if (!is_string($prose) || trim($prose) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'prose required',
    ]);
}

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {

    $result = execute_calendar_batch_from_prose(
        $pdo,
        $parentEventEntityId,
        $prose
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'batch' => $result,
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
        'error' => 'Failed to execute calendar batch',
        'database' => $expectedDatabase,
    ], $e);
}