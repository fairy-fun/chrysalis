<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/reference/list_team_roles.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(405, [
            'status' => 'error',
            'error' => 'Method not allowed',
        ]);
    }

    requireAuth();

    $pdo = makePdo();
    $database = verifyExpectedDatabase($pdo);

    $roles = list_team_roles($pdo);

    respond(200, [
        'status' => 'ok',
        'database' => $database,
        'data' => [
            'roles' => $roles,
        ],
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to list team roles',
    ], $e);
}