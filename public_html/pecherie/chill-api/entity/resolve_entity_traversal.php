<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/traversal/traversal_definition_loader.php';
require_once __DIR__ . '/../../../../private/framework/traversal/traversal_plan_builder.php';
require_once __DIR__ . '/../../../../private/framework/traversal/traversal_sql_emitter.php';
require_once __DIR__ . '/../../../../private/framework/traversal/execute_entity_traversal.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$pathId = (int)($body['path_id'] ?? 0);

if ($pathId < 1) {
    respond(400, [
        'status' => 'error',
        'error' => 'path_id must be a positive integer',
    ]);
}

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $rows = execute_entity_traversal($pdo, $pathId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'path_id' => $pathId,
        'row_count' => count($rows),
        'rows' => $rows,
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
        'error' => 'Failed to resolve entity traversal',
        'database' => $expectedDatabase,
    ], $e);
}