<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/choreography/medley_lookup.php';
require_once __DIR__ . '/../../../../private/framework/choreography/medley_core_reader.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$rawMedleyId = $body['medley_id'] ?? null;
$rawMedleyName = $body['medley_name'] ?? null;

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $medleyId = resolve_medley_id($pdo, $rawMedleyId, $rawMedleyName);
    $rows = resolve_medley_core($pdo, $medleyId);

    $response = [
        'status' => 'ok',
        'source' => 'database',
        'database' => $expectedDatabase,
        'medley_id' => $medleyId,
        'row_count' => count($rows),
        'rows' => $rows,
    ];

    if (is_string($rawMedleyName) && trim($rawMedleyName) !== '') {
        $response['requested_medley_name'] = trim($rawMedleyName);
    }

    respond(200, $response);
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
        'error' => 'Failed to resolve medley core',
        'database' => $expectedDatabase,
    ], $e);
}