<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = $_QUERY_BODY ?? [];
$chronologyAddress = $body['chronology_address'] ?? null;

if (!is_string($chronologyAddress) || trim($chronologyAddress) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'chronology_address must be a non-empty string',
    ]);
}

$chronologyAddress = trim($chronologyAddress);

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $stmt = $pdo->prepare("
        SELECT
            ce.entity_id,
            ce.event_id,
            ce.parent_event_id,
            ce.layer_id,
            ce.sequence_index,
            ce.summary,
            ce.created_at,
            ce.updated_at,
            e.entity_type_id,
            ce.chronology_address
        FROM sxnzlfun_chrysalis.calendar_events ce
        INNER JOIN sxnzlfun_chrysalis.entities e
            ON e.id = ce.entity_id
        WHERE ce.chronology_address = :chronology_address
        LIMIT 1
    ");

    $stmt->execute([
        ':chronology_address' => $chronologyAddress,
    ]);

    $node = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($node)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Calendar node not found for chronology_address',
            'database' => $expectedDatabase,
        ]);
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'node' => $node,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to fetch calendar node by chronology_address',
        'database' => $expectedDatabase,
    ], $e);
}