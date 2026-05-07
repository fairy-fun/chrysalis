<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$parentDayEntityId = $_GET['parent_day_entity_id'] ?? null;

if (!is_string($parentDayEntityId) || trim($parentDayEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_day_entity_id must be a non-empty string',
    ]);
}

$parentDayEntityId = trim($parentDayEntityId);

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $parent = $pdo->prepare("
        SELECT
            ce.`id` AS _internal_id,
            ce.entity_id,
            cp.entity_id AS projection_entity_id,
            ce.layer_id,
            ce.week_index,
            ce.day_index,
            ce.chronology_address
        FROM sxnzlfun_chrysalis.calendar_events ce
        LEFT JOIN sxnzlfun_chrysalis.calendar_projections cp
            ON cp.id = ce.projection_id
        WHERE ce.entity_id = :entity_id
          AND ce.layer_id = 'calendar_layer_day'
        LIMIT 1
    ");

    $parent->execute([':entity_id' => $parentDayEntityId]);

    $day = $parent->fetch(PDO::FETCH_ASSOC);

    if (!is_array($day)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Parent day not found',
            'database' => $expectedDatabase,
        ]);
    }

    $stmt = $pdo->prepare("
        SELECT
            ce.entity_id,
            ce.event_id,
            cp.entity_id AS projection_entity_id,
            ce.layer_id,
        
            ce.sequence_index,
            ce.time_index,
            ce.time_label_id,
        
            tl.code AS time_label_code,
            tl.label AS time_label,
            tl.sort_order AS time_sort_order,
        
            ce.summary,
            ce.created_at,
            ce.updated_at,
            ce.chronology_address
        
        FROM sxnzlfun_chrysalis.calendar_events ce
        
        LEFT JOIN sxnzlfun_chrysalis.calendar_projections cp
            ON cp.id = ce.projection_id
        
        LEFT JOIN sxnzlfun_chrysalis.calendar_time_label_classvals tl
            ON tl.id = ce.time_label_id
        
        WHERE ce.parent_event_id = :parent_event_id
          AND ce.layer_id = 'calendar_layer_time'
        
        ORDER BY
            tl.sort_order ASC,
            ce.sequence_index ASC,
            ce.event_id ASC
    ");

    $stmt->execute([':parent_event_id' => (int)$day['_internal_id']]);

    $times = $stmt->fetchAll(PDO::FETCH_ASSOC);

    unset($day['_internal_id']);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'day' => $day,
        'times' => $times,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to fetch calendar times for day',
        'database' => $expectedDatabase,
    ], $e);
}