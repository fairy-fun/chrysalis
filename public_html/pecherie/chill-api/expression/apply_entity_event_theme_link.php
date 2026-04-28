<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/expression/entity_event_theme_link_applier.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$proposal = $body['proposal'] ?? null;

if (!is_array($proposal)) {
    respond(400, [
        'status' => 'error',
        'error' => 'proposal must be an object',
    ]);
}

$pdo = makePdo();
$database = verifyExpectedDatabase($pdo);

try {
    $result = apply_entity_event_theme_link_proposal($pdo, $proposal);

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
        'error' => 'Failed to apply event theme link proposal',
        'database' => $database,
    ], $e);
}