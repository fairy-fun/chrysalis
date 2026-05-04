<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$parentWeekEntityId = $_GET['parent_week_entity_id'] ?? null;

if (!is_string($parentWeekEntityId) || trim($parentWeekEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_week_entity_id must be a non-empty string',
    ]);
}

$parentWeekEntityId = trim($parentWeekEntityId);

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $parent = $pdo->prepare("
        SELECT
            ce.`id` AS _internal_id,
            ce.entity_id,
            COALESCE(ce.projection_entity_id, cepm.projection_entity_id) AS projection_entity_id,
            ce.layer_id,
            ce.week_index,
            ce.chronology_address
        FROM sxnzlfun_chrysalis.calendar_events ce
        LEFT JOIN sxnzlfun_chrysalis.calendar_event_projection_membership cepm
            ON cepm.calendar_event_id = ce.`id`
        WHERE ce.entity_id = :entity_id
          AND ce.layer_id = 'calendar_layer_week'
        LIMIT 1
    ");

    $parent->execute([':entity_id' => $parentWeekEntityId]);

    $week = $parent->fetch(PDO::FETCH_ASSOC);

    if (!is_array($week)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Parent week not found',
            'database' => $expectedDatabase,
        ]);
    }

    $daysStmt = $pdo->prepare("
        SELECT
            ce.entity_id,
            ce.event_id,
            ce.projection_entity_id,
            ce.layer_id,
            ce.sequence_index,
            ce.summary,
            ce.created_at,
            ce.updated_at,
            ce.chronology_address
        FROM sxnzlfun_chrysalis.calendar_events ce
        WHERE ce.parent_event_id = :parent_event_id
          AND ce.layer_id = 'calendar_layer_day'
        ORDER BY ce.sequence_index ASC, ce.event_id ASC
    ");

    $daysStmt->execute([':parent_event_id' => (int)$week['_internal_id']]);

    $days = $daysStmt->fetchAll(PDO::FETCH_ASSOC);

    unset($week['_internal_id']);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'week' => $week,
        'days' => $days,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to fetch calendar days for week',
        'database' => $expectedDatabase,
    ], $e);
}