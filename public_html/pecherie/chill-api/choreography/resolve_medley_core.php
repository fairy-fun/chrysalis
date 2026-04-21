<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/choreography/medley_core_reader.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

if (!array_key_exists('medley_id', $body)) {
    respond(400, ['error' => 'Missing medley_id']);
}

$medleyId = $body['medley_id'];

if (!is_int($medleyId) || $medleyId < 1) {
    respond(400, ['error' => 'medley_id must be a positive integer']);
}

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $rows = resolve_medley_core($pdo, $medleyId);

    respond(200, [
        'status' => 'ok',
        'source' => 'database',
        'database' => $expectedDatabase,
        'medley_id' => $medleyId,
        'row_count' => count($rows),
        'rows' => $rows,
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to resolve medley core',
        'database' => $expectedDatabase,
        'medley_id' => $medleyId,
    ], $e);
}