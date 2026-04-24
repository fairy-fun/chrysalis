<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/audit/audit_event_graph_identity.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(405, [
            'status' => 'error',
            'error' => 'Method not allowed',
        ]);
    }

    requireAuth();

    $pdo = makePdo();
    $schemaName = verifyExpectedDatabase($pdo);

    $audit = audit_event_graph_identity($pdo, $schemaName);

    if ($audit['ok'] !== true) {
        respond(409, [
            'status' => 'error',
            'error' => 'Event graph identity validation failed.',
            'data' => $audit,
        ]);
    }

    respond(200, [
        'status' => 'ok',
        'data' => $audit,
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Event graph identity audit failed',
    ], $e);
}
