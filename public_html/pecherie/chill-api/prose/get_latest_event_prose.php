<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/prose/latest_event_prose_resolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

try {

    $body = getJsonBody();

    $projectionId = $body['projection_id'] ?? null;

    if (
        !is_int($projectionId)
        && !(
            is_string($projectionId)
            && ctype_digit($projectionId)
        )
    ) {
        throw new InvalidArgumentException(
            'projection_id is required'
        );
    }

    $projectionId = (int)$projectionId;

    $pdo = makePdo('read');

    $expectedDatabase = verifyExpectedDatabase($pdo);

    $result = resolve_latest_event_prose(
        $pdo,
        $projectionId
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'projection_id' => $projectionId,
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
        'error' => 'Failed to resolve latest event prose',
    ], $e);
}