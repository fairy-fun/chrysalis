<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/prose/event_prose_resolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

try {
    $body = getJsonBody();

    $calendarEventEntityId = $body['calendar_event_entity_id'] ?? null;

    if (!is_string($calendarEventEntityId) || trim($calendarEventEntityId) === '') {
        throw new InvalidArgumentException(
            'calendar_event_entity_id is required'
        );
    }

    $calendarEventEntityId = trim($calendarEventEntityId);

    $pdo = makePdo('read');

    $expectedDatabase = verifyExpectedDatabase($pdo);

    $result = resolve_event_prose(
        $pdo,
        $calendarEventEntityId
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'calendar_event_entity_id' => $calendarEventEntityId,
        'result' => $result,
    ]);
} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to resolve event prose',
    ], $e);
}
