<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../private/db.php';
require_once __DIR__ . '/../../../../private/framework/limbic/limbic_fact_writer.php';

$body = read_request_body();

try {
    $pdo = db();
    $result = apply_limbic_fact($pdo, $body);

    echo json_encode([
        'status' => 'ok',
        'operation' => 'applyLimbicFact',
        'result' => $result,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    api_error(400, $e->getMessage());
}