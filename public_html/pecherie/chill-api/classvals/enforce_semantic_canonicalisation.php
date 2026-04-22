<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/private/framework/api/api_bootstrap.php';
require_once dirname(__DIR__, 4) . '/private/framework/classvals/semantic_canonicalisation.php';

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

try {
    $pdo = makePdo();

    assertAllClassvalSemanticCanonicalRules($pdo);

    respond(200, [
        'status' => 'ok',
        'message' => 'No deprecated semantic duplicates found in classvals',
    ]);
} catch (RuntimeException $e) {
    respond(409, [
        'status' => 'error',
        'error' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    respond(500, [
        'status' => 'error',
        'error' => 'Internal error',
    ]);
}