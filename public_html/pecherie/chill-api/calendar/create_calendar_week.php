<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_week_creator.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$bookCode = $body['book_code'] ?? null;
$weekIndex = $body['week_index'] ?? null;
$weekLabel = $body['week_label'] ?? null;
$realDateStartId = $body['real_date_start_id'] ?? null;

/**
 * Validation — mirror existing API style exactly
 */

if (!is_string($bookCode) || trim($bookCode) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'book_code must be a non-empty string',
    ]);
}

if (!is_int($weekIndex) || $weekIndex < 1) {
    respond(400, [
        'status' => 'error',
        'error' => 'week_index must be a positive integer',
    ]);
}

if (!is_string($weekLabel) || trim($weekLabel) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'week_label must be a non-empty string',
    ]);
}

if (!is_string($realDateStartId) || trim($realDateStartId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'real_date_start_id must be a non-empty string',
    ]);
}

$bookCode = trim($bookCode);
$weekLabel = trim($weekLabel);
$realDateStartId = trim($realDateStartId);

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $result = create_calendar_week_for_book(
        $pdo,
        $bookCode,
        $weekIndex,
        $weekLabel,
        $realDateStartId
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'book_code' => $bookCode,
        'week_index' => $weekIndex,
        'result' => $result,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (RuntimeException $e) {
    respond(409, [ // conflict / invariant violation
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to create calendar week',
        'database' => $expectedDatabase,
    ], $e);
}