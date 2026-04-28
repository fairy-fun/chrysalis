<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/expression/expression_pipeline_runner.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$characterId = $body['character_id'] ?? null;
$contextEntityId = $body['context_entity_id'] ?? null;
$asOfTs = $body['as_of_ts'] ?? null;

if (!is_string($characterId) || trim($characterId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'character_id must be a non-empty string',
    ]);
}

if (!is_string($contextEntityId) || trim($contextEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'context_entity_id must be a non-empty string',
    ]);
}

$pdo = makePdo();
$database = verifyExpectedDatabase($pdo);

try {
    $result = run_expression_pipeline(
        $pdo,
        trim($characterId),
        trim($contextEntityId),
        is_string($asOfTs) ? trim($asOfTs) : null
    );

    respond(200, [
        'status' => 'ok',
        'database' => $database,
        'data' => $result,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $database,
    ]);

} catch (RuntimeException $e) {
    respond(404, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $database,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to run expression pipeline',
        'database' => $database,
    ], $e);
}