<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/traversal/execute_frontier_entity_traversal.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$pathId = (int)($body['path_id'] ?? 0);
$startEntityId = (int)($body['start_entity_id'] ?? 0);

if ($pathId < 1) {
    respond(400, ['status' => 'error', 'error' => 'path_id must be a positive integer']);
}

if ($startEntityId < 1) {
    respond(400, ['status' => 'error', 'error' => 'start_entity_id must be a positive integer']);
}

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $result = execute_frontier_entity_traversal($pdo, $pathId, $startEntityId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'path_id' => $pathId,
        'start_entity_id' => $startEntityId,
        'frontier_count' => count($result['frontier']),
        'frontier' => $result['frontier'],
        'projections' => $result['projections'],
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to execute entity traversal',
        'database' => $expectedDatabase
    ], $e);
}