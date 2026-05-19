<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$userMessage = $body['message'] ?? null;
$sessionId = $body['session_id'] ?? null;

if (!is_string($userMessage) || trim($userMessage) === '') {

    respond(400, [
        'status' => 'error',
        'error' => 'message must be a non-empty string',
    ]);
}

if ($sessionId !== null && !is_string($sessionId)) {

    respond(400, [
        'status' => 'error',
        'error' => 'session_id must be a string when provided',
    ]);
}

$pdo = makePdo('write');

try {

    $result = fw_start_chat_request(
        $pdo,
        trim($userMessage),
        is_string($sessionId) ? trim($sessionId) : null
    );

    respond(200, $result);
} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ], $e);
}

/*} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to start or continue workflow chat',
    ], $e);
}*/