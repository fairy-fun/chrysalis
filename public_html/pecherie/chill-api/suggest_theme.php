<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../private/framework/expression/entity_event_theme_link_suggester.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$contextEntityId = $body['context_entity_id'] ?? null;

if ($contextEntityId !== null && (!is_string($contextEntityId) || trim($contextEntityId) === '')) {
    respond(400, [
        'status' => 'error',
        'error' => 'context_entity_id must be a non-empty string or null',
    ]);
}

$pdo = makePdo();
$database = verifyExpectedDatabase($pdo);

try {
    $result = suggest_entity_event_theme_link(
        $pdo,
        is_string($contextEntityId) ? trim($contextEntityId) : null
    );

    respond(200, [
        'status' => 'ok',
        'database' => $database,
        'data' => $result,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to generate theme suggestions',
        'database' => $database,
    ], $e);
}