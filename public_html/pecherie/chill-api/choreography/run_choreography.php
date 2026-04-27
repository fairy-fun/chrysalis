<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/choreography/run_choreography.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$startEntityId = (int)($body['start_entity_id'] ?? 0);
$startEntityId = (string)($body['start_entity_id'] ?? '');

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $result = run_choreography($pdo, $startEntityId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        ...$result,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (RuntimeException $e) {
    respond(404, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to run choreography traversal',
        'database' => $expectedDatabase,
    ], $e);
}
