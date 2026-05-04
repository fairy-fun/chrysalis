<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_layer_ensurers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$parentDayEntityId = $body['parent_day_entity_id'] ?? null;
$timeIndex = $body['time_index'] ?? null;
$timeLabel = $body['time_label'] ?? null;
$timeLabelId = $body['time_label_id'] ?? null;

if ($timeLabel !== null && !is_string($timeLabel)) {
    respond(400, [
        'status' => 'error',
        'error' => 'time_label must be a string when provided',
    ]);
}

if ($timeLabelId !== null && !is_string($timeLabelId)) {
    respond(400, [
        'status' => 'error',
        'error' => 'time_label_id must be a string when provided',
    ]);
}

$parentDayEntityId = is_string($parentDayEntityId) ? trim($parentDayEntityId) : $parentDayEntityId;
$timeLabel = is_string($timeLabel) ? trim($timeLabel) : null;
$timeLabelId = is_string($timeLabelId) ? trim($timeLabelId) : null;

if (!is_string($parentDayEntityId) || $parentDayEntityId === '') {
    respond(400, ['status' => 'error', 'error' => 'parent_day_entity_id required']);
}

if (!is_int($timeIndex) || $timeIndex < 1) {
    respond(400, ['status' => 'error', 'error' => 'time_index must be positive integer']);
}

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $payload = [
        'summary' => $timeLabel !== null && $timeLabel !== ''
            ? $timeLabel
            : 'Time',
    ];

    if ($timeLabelId !== null && $timeLabelId !== '') {
        $stmt = $pdo->prepare("
            SELECT label
            FROM sxnzlfun_chrysalis.calendar_time_label_classvals
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $timeLabelId,
        ]);

        $classvalLabel = $stmt->fetchColumn();

        if (!is_string($classvalLabel) || trim($classvalLabel) === '') {
            throw new InvalidArgumentException('Invalid time_label_id');
        }

        $payload['time_label_id'] = $timeLabelId;
        $payload['summary'] = trim($classvalLabel);
    }

    $time = ensure_calendar_time(
        $pdo,
        $parentDayEntityId,
        $timeIndex,
        $payload
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