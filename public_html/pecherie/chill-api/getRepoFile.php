<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
if ($raw === false) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'Unable to read request body',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'Invalid JSON body',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$body['operation'] = 'getRepoFile';

$GLOBALS['_API_BODY'] = $body;
$GLOBALS['_QUERY_BODY'] = $body;

require __DIR__ . '/index.php';