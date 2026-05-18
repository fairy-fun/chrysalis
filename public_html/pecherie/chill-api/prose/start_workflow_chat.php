<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    respond(405, [
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$userMessage = $body['message'] ?? null;

if (!is_string($userMessage) || trim($userMessage) === '') {

    respond(400, [
        'status' => 'error',
        'error' => 'message must be a non-empty string',
    ]);
}

$pdo = makePdo('write');

try {

    $result = fw_start_workflow_from_chat(
        $pdo,
        trim($userMessage)
    );

    respond(200, [
        'status' => 'ok',
        'result' => $result,
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'error' => 'Failed to start workflow from chat',
    ], $e);
}