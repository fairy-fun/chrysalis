<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/prose/prose_export_resolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

try {

    $body = getJsonBody();

    $exportTargetKey = $body['export_target_key'] ?? null;

    if (
        !is_string($exportTargetKey)
        || trim($exportTargetKey) === ''
    ) {
        throw new InvalidArgumentException(
            'export_target_key is required'
        );
    }

    $exportTargetKey = trim($exportTargetKey);

    $pdo = makePdo('read');

    $expectedDatabase = verifyExpectedDatabase($pdo);

    $rows = resolve_prose_export_text(
        $pdo,
        $exportTargetKey
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'export_target_key' => $exportTargetKey,
        'export_count' => count($rows),
        'exports' => $rows,
    ]);

} catch (InvalidArgumentException $e) {

    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to resolve prose export text',
    ], $e);
}