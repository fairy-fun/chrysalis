<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/team/list_team_members.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, [
            'status' => 'error',
            'error' => 'Method not allowed',
        ]);
    }

    requireAuth();

    $body = getJsonBody();
    $teamEntityId = $body['team_entity_id'] ?? null;

    if (!is_string($teamEntityId) || trim($teamEntityId) === '') {
        respond(400, [
            'status' => 'error',
            'error' => 'Missing team_entity_id',
        ]);
    }

    $pdo = makePdo();
    verifyExpectedDatabase($pdo);

    $members = list_team_members($pdo, trim($teamEntityId));

    respond(200, [
        'status' => 'ok',
        'data' => [
            'team_entity_id' => trim($teamEntityId),
            'members' => $members,
        ],
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to list team members',
    ], $e);
}