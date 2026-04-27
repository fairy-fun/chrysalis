<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../../private/framework/choreography/performance_routine_creator.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

$result = create_performance_routine(
    $pdo,
    $body['team_id'],
    $body['choreography_type_id'],
    $body['status_classval_id'],
    $body['year_id'],
    $body['medley_id'] ?? null,
    $body['routine_name'],
    $body['music_title'] ?? null,
    $body['duration_seconds'] ?? null,
    $body['notes'] ?? null,
    $body['source_document'] ?? null
);

respond(200, [
    'status' => 'ok',
    'database' => $expectedDatabase,
    'result' => $result,
]);