<?php


declare(strict_types=1);

require_once __DIR__ . '/../api_bootstrap.php';

require_once __DIR__ . '/../../../../private/framework/facts/apply_fact.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);

    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON body'
    ]);

    exit;
}

$required = [
    'subject_entity_id',
    'fact_type_id',
    'object_entity_id',
    'source_document'
];

foreach ($required as $field) {
    if (
        !array_key_exists($field, $body)
        || $body[$field] === null
        || $body[$field] === ''
    ) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => "Missing required field: {$field}"
        ]);

        exit;
    }
}

$pdo = db();

verifyExpectedDatabase($pdo);

$result = apply_global_fact(
    $pdo,
    $body['subject_entity_id'],
    $body['fact_type_id'],
    $body['object_entity_id'],
    $body['source_document'],
    $body['notes'] ?? null,
    $body['governance'] ?? [],
    $body['supersedes_linked_fact_id'] ?? null
);

echo json_encode([
    'status' => 'ok',
    'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
    'result' => $result
]);