<?php
declare(strict_types=1);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];
$endpoint = $body['endpoint'] ?? '';

if ($endpoint === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown endpoint: ', 'raw_received' => $raw]);
    exit;
}

switch ($endpoint) {
    case 'tables':
        $_SERVER['REQUEST_METHOD'] = 'GET';
        require __DIR__ . '/tables.php';
        break;
    case 'columns':
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['table'] = $body['table'] ?? '';
        require __DIR__ . '/columns.php';
        break;
    case 'query':
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $GLOBALS['_QUERY_BODY'] = $body;
        require __DIR__ . '/query.php';
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown endpoint: ' . $endpoint]);
        exit;
}