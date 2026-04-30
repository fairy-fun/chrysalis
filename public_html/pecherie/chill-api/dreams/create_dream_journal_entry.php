<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/dreams/dream_journal_entry_creator.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $result = create_dream_journal_entry($pdo, getJsonBody());

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'dream' => $result['dream'],
        'prose' => $result['prose'],
        'projection' => $result['projection'],
        'annotations' => $result['annotations'],
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (RuntimeException $e) {
    respond(409, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to create dream journal entry',
        'database' => $expectedDatabase,
    ], $e);
}