<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/dreams/dream_journal_reader.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();
$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $dreamEntityId = prose_reader_required_string($body, 'dream_entity_id');

    $result = get_dream_annotations($pdo, $dreamEntityId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'dream_entity_id' => $result['dream_entity_id'],
        'prose_entity_id' => $result['prose_entity_id'],
        'annotations' => $result['annotations'],
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
        'error' => 'Failed to fetch dream annotations',
        'database' => $expectedDatabase,
    ], $e);
}