<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

function resolve_book_day_for_time_listing(
    PDO $pdo,
    string $dayIdentity
): array {
    $canonicalDayStmt = $pdo->prepare("
        SELECT
            cbd.id,
            cbd.entity_id,
            cp.entity_id AS projection_entity_id,
            cbd.projection_id,
            cbd.week_id,
            cbd.day_index,
            cbd.day_of_week_id,
            cbd.summary,
            cbd.notes,
            cbd.real_date_start_id,
            cbd.real_date_end_id,
            cbd.created_at,
            cbd.updated_at,
            cbw.week_index
        FROM sxnzlfun_chrysalis.calendar_book_days cbd
        INNER JOIN sxnzlfun_chrysalis.calendar_book_weeks cbw
            ON cbw.id = cbd.week_id
        INNER JOIN sxnzlfun_chrysalis.calendar_projections cp
            ON cp.id = cbd.projection_id
        WHERE cbd.entity_id = :entity_id
        LIMIT 1
    ");

    $canonicalDayStmt->execute([
        ':entity_id' => $dayIdentity,
    ]);

    $canonicalDay = $canonicalDayStmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($canonicalDay)) {
        return $canonicalDay;
    }

    $legacyDayStmt = $pdo->prepare("
        SELECT
            projection_id,
            parent_event_id,
            sequence_index,
            chronology_address
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_day'
        LIMIT 1
    ");

    $legacyDayStmt->execute([
        ':entity_id' => $dayIdentity,
    ]);

    $legacyDay = $legacyDayStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($legacyDay)) {
        throw new RuntimeException('Parent day not found');
    }

    $projectionId = (int)($legacyDay['projection_id'] ?? 0);
    $parentWeekId = (int)($legacyDay['parent_event_id'] ?? 0);
    $dayIndex = (int)($legacyDay['sequence_index'] ?? 0);

    if ($projectionId < 1 || $parentWeekId < 1 || $dayIndex < 1) {
        throw new RuntimeException('Legacy parent day is missing canonical locality fields');
    }

    $legacyWeekStmt = $pdo->prepare("
        SELECT sequence_index
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE id = :parent_week_id
          AND layer_id = 'calendar_layer_week'
        LIMIT 1
    ");

    $legacyWeekStmt->execute([
        ':parent_week_id' => $parentWeekId,
    ]);

    $weekIndex = (int)$legacyWeekStmt->fetchColumn();

    if ($weekIndex < 1) {
        throw new RuntimeException('Legacy parent week is missing canonical locality fields');
    }

    $canonicalFromLegacyStmt = $pdo->prepare("
        SELECT
            cbd.id,
            cbd.entity_id,
            cp.entity_id AS projection_entity_id,
            cbd.projection_id,
            cbd.week_id,
            cbd.day_index,
            cbd.day_of_week_id,
            cbd.summary,
            cbd.notes,
            cbd.real_date_start_id,
            cbd.real_date_end_id,
            cbd.created_at,
            cbd.updated_at,
            cbw.week_index
        FROM sxnzlfun_chrysalis.calendar_book_days cbd
        INNER JOIN sxnzlfun_chrysalis.calendar_book_weeks cbw
            ON cbw.id = cbd.week_id
        INNER JOIN sxnzlfun_chrysalis.calendar_projections cp
            ON cp.id = cbd.projection_id
        WHERE cbd.projection_id = :projection_id
          AND cbw.week_index = :week_index
          AND cbd.day_index = :day_index
        LIMIT 1
    ");

    $canonicalFromLegacyStmt->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
        ':day_index' => $dayIndex,
    ]);

    $canonicalFromLegacy = $canonicalFromLegacyStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($canonicalFromLegacy)) {
        throw new RuntimeException('Canonical Book day not found for legacy parent day');
    }

    return $canonicalFromLegacy;
}

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
    $day = resolve_book_day_for_time_listing(
        $pdo,
        $parentDayEntityId
    );

    $stmt = $pdo->prepare("
        SELECT
            t.entity_id,
            cp.entity_id AS projection_entity_id,
            'calendar_book_time' AS calendar_structure_type,
            t.projection_id,
            t.day_id,
            t.time_index,
            t.time_label_id,
            tl.code AS time_label_code,
            tl.label AS time_label,
            tl.sort_order AS time_sort_order,
            t.summary,
            t.notes,
            t.created_at,
            t.updated_at
        FROM sxnzlfun_chrysalis.calendar_book_times t
        INNER JOIN sxnzlfun_chrysalis.calendar_projections cp
            ON cp.id = t.projection_id
        LEFT JOIN sxnzlfun_chrysalis.calendar_time_label_classvals tl
            ON tl.id = t.time_label_id
        WHERE t.day_id = :day_id
          AND t.projection_id = :projection_id
        ORDER BY t.time_index ASC
    ");

    $stmt->execute([
        ':day_id' => (int)$day['id'],
        ':projection_id' => (int)$day['projection_id'],
    ]);

    $times = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
