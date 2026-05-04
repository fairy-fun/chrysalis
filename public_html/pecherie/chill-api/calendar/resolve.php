<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_chronology_resolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$address = $_GET['chronology_address'] ?? null;

if (!is_string($address) || trim($address) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'chronology_address must be a non-empty string',
    ]);
}

$address = trim($address);

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $entityId = resolve_calendar_chronology_path($pdo, $address);

    if ($entityId === null) {
        respond(404, [
            'status' => 'error',
            'error' => 'Chronology path not found',
            'chronology_address' => $address,
            'database' => $expectedDatabase,
        ]);
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'chronology_address' => $address,
        'entity_id' => $entityId,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to resolve calendar chronology address',
        'database' => $expectedDatabase,
    ], $e);
}