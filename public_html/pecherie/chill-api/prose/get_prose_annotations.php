<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/prose/prose_annotation_reader.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();
$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $entityId = prose_reader_required_string($body, 'entity_id');

    $annotations = get_prose_annotations($pdo, $entityId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'prose_entity_id' => $entityId,
        'annotations' => $annotations,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (RuntimeException $e) {
    respond(404, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to fetch prose annotations',
        'database' => $expectedDatabase,
    ], $e);
}