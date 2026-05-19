<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

require_once __DIR__
    . '/../../../../private/framework/prose/prose_chronology_resolver.php';

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
    $chronologyAddress = $body['chronology_address'] ?? null;

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

    if (
        !is_string($chronologyAddress)
        || trim($chronologyAddress) === ''
    ) {
        throw new InvalidArgumentException(
            'chronology_address is required'
        );
    }

    $pdo = makePdo('read');

    $expectedDatabase = verifyExpectedDatabase($pdo);

    $result = resolve_prose_by_chronology_address(
        $pdo,
        (int)$projectionId,
        trim($chronologyAddress)
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'projection_id' => (int)$projectionId,
        'chronology_address' => trim($chronologyAddress),
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
        'error' => 'Failed to resolve prose by chronology',
    ], $e);
}