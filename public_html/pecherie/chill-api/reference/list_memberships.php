<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/reference/list_memberships.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(405, [
            'status' => 'error',
            'error' => 'Method not allowed',
        ]);
    }

    requireAuth();

    $pdo = makePdo();
    verifyExpectedDatabase($pdo);

    $memberships = list_memberships($pdo);

    respond(200, [
        'status' => 'ok',
        'data' => $memberships,
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to list memberships',
    ], $e);
}