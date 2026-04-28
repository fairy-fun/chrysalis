<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../private/framework/expression/character_theme_progression_builder.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$characterEntityId = $body['character_entity_id'] ?? null;
$projectionEntityId = $body['projection_entity_id'] ?? null;

if (!is_string($characterEntityId) || trim($characterEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'character_entity_id is required and must be a non-empty string',
    ]);
}

if ($projectionEntityId !== null && (!is_string($projectionEntityId) || trim($projectionEntityId) === '')) {
    respond(400, [
        'status' => 'error',
        'error' => 'projection_entity_id must be a non-empty string or null',
    ]);
}

$pdo = makePdo();
$database = verifyExpectedDatabase($pdo);

try {
    $result = buildCharacterThemeProgression(
        $pdo,
        trim($characterEntityId),
        is_string($projectionEntityId) ? trim($projectionEntityId) : null
    );

    respond(200, [
        'status' => 'ok',
        'database' => $database,
        'data' => $result,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to build character theme progression',
        'database' => $database,
    ], $e);
}
