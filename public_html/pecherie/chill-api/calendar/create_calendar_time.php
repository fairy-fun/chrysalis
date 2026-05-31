<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/admin/calendar_book_chronology_materializer.php';
require_once __DIR__ . '/../../../../private/framework/classvals/classval_validation.php';

function resolve_book_day_for_calendar_time_creation(
    PDO $pdo,
    string $dayIdentity
): array {
    $canonicalDayStmt = $pdo->prepare("
        SELECT
            cbd.id,
            cbd.entity_id,
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
            sequence_index
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

function reload_calendar_book_time_response(PDO $pdo, int $timeId): array
{
    $stmt = $pdo->prepare("
        SELECT
            t.id,
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
        WHERE t.id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $timeId,
    ]);

    $time = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($time)) {
        throw new RuntimeException('Created Book time not found');
    }

    return $time;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$parentDayEntityId = $body['parent_day_entity_id'] ?? null;
$timeIndex = $body['time_index'] ?? null;
$timeLabelId = $body['time_label_id'] ?? null;

$parentDayEntityId = is_string($parentDayEntityId) ? trim($parentDayEntityId) : $parentDayEntityId;
$timeLabelId = is_string($timeLabelId) ? trim($timeLabelId) : $timeLabelId;

if (!is_string($parentDayEntityId) || $parentDayEntityId === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_day_entity_id required',
    ]);
}

if (!is_int($timeIndex) || $timeIndex < 1) {
    respond(400, [
        'status' => 'error',
        'error' => 'time_index must be positive integer',
    ]);
}

if (!is_string($timeLabelId) || $timeLabelId === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'time_label_id required',
    ]);
}

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    assert_valid_classval(
        $pdo,
        'calendar_time_label_classvals',
        $timeLabelId,
        'time_label_id'
    );

    $labelStmt = $pdo->prepare("
        SELECT label
        FROM sxnzlfun_chrysalis.calendar_time_label_classvals
        WHERE id = :id
        LIMIT 1
    ");

    $labelStmt->execute([
        ':id' => $timeLabelId,
    ]);

    $classvalLabel = $labelStmt->fetchColumn();

    if (!is_string($classvalLabel) || trim($classvalLabel) === '') {
        throw new InvalidArgumentException('Invalid time_label_id');
    }

    $bookDay = resolve_book_day_for_calendar_time_creation(
        $pdo,
        $parentDayEntityId
    );

    $time = materialize_calendar_book_time(
        $pdo,
        (int)$bookDay['projection_id'],
        (int)$bookDay['id'],
        $timeIndex
    );

    $updateStmt = $pdo->prepare("
        UPDATE sxnzlfun_chrysalis.calendar_book_times
        SET time_label_id = :time_label_id,
            summary = :summary,
            updated_at = NOW()
        WHERE id = :id
    ");

    $updateStmt->execute([
        ':time_label_id' => $timeLabelId,
        ':summary' => trim($classvalLabel),
        ':id' => (int)$time['id'],
    ]);

    $time = reload_calendar_book_time_response(
        $pdo,
        (int)$time['id']
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'time' => $time,
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
        'error' => 'Failed to create calendar time',
        'database' => $expectedDatabase,
    ], $e);
}
