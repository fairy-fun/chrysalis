<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/traversal/traversal_definition_loader.php';
require_once __DIR__ . '/../../../../private/framework/traversal/traversal_definition_validator.php';
require_once __DIR__ . '/../../../../private/framework/traversal/traversal_plan_builder.php';
require_once __DIR__ . '/../../../../private/framework/traversal/traversal_sql_emitter.php';
require_once __DIR__ . '/../../../../private/framework/traversal/execute_entity_traversal.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$pathId = (int)($body['path_id'] ?? 0);
$mode = (string)($body['mode'] ?? 'execute');

if ($pathId < 1) {
    respond(400, [
        'status' => 'error',
        'error' => 'path_id must be a positive integer',
    ]);
}

if (!in_array($mode, ['execute', 'debug'], true)) {
    respond(400, [
        'status' => 'error',
        'error' => 'Invalid mode',
    ]);
}

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $result = resolve_entity_traversal_full($pdo, $pathId);

    if ($mode === 'debug') {
        respond(200, [
            'status' => 'ok',
            'database' => $expectedDatabase,
            'path_id' => $pathId,
            'plan' => $result['plan'],
            'sql' => $result['sql'],
            'row_count' => count($result['rows']),
            'rows' => $result['rows'],
        ]);
        return;
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'path_id' => $pathId,
        'row_count' => count($result['rows']),
        'rows' => $result['rows'],
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
