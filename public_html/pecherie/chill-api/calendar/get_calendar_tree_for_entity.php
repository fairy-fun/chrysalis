<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = $_QUERY_BODY ?? [];
$entityId = $body['entity_id'] ?? null;

if (!is_string($entityId) || trim($entityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'entity_id must be a non-empty string',
    ]);
}

$entityId = trim($entityId);

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    // --- Fetch root node ---
    $rootStmt = $pdo->prepare("
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
        WHERE ce.entity_id = :entity_id
        LIMIT 1
    ");

    $rootStmt->execute([
        ':entity_id' => $entityId,
    ]);

    $root = $rootStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($root)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Calendar node not found',
            'database' => $expectedDatabase,
        ]);
    }

    // --- Validate it's a calendar node ---
    if (!str_starts_with($root['entity_type_id'], 'entity_type_calendar_')) {
        respond(409, [
            'status' => 'error',
            'error' => 'Entity is not a calendar node',
            'database' => $expectedDatabase,
        ]);
    }

    if (!str_starts_with($root['layer_id'], 'calendar_layer_')) {
        respond(409, [
            'status' => 'error',
            'error' => 'Invalid calendar layer',
            'database' => $expectedDatabase,
        ]);
    }

    // --- Recursive builder ---
    $fetchChildren = function (int $parentEventId) use ($pdo, &$fetchChildren): array {
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
            WHERE ce.parent_event_id = :parent_event_id
            ORDER BY ce.sequence_index ASC, ce.event_id ASC
        ");

        $stmt->execute([
            ':parent_event_id' => $parentEventId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $children = [];

        foreach ($rows as $row) {
            $row['children'] = $fetchChildren((int)$row['event_id']);
            $children[] = $row;
        }

        return $children;
    };

    // --- Build full tree ---
    $root['children'] = $fetchChildren((int)$root['event_id']);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'root' => $root,
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'error' => 'Failed to fetch calendar tree',
        'database' => $expectedDatabase,
    ], $e);
}