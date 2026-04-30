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

$journalEntityId = $body['journal_entity_id'] ?? null;

if (!is_string($journalEntityId) || trim($journalEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'journal_entity_id is required',
    ]);
}

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $entries = get_dream_journal_entries($pdo, $journalEntityId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'entries' => $entries,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to fetch dream journal entries',
        'database' => $expectedDatabase,
    ], $e);
}