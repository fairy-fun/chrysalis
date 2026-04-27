<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../../private/framework/choreography/performance_routine_creator.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$teamId = $body['team_id'] ?? null;
$yearValue = $body['year_value'] ?? null;
$routineName = $body['routine_name'] ?? null;
$choreographyTypeId = $body['choreography_type_id'] ?? null;
$musicTitle = $body['music_title'] ?? null;
$durationSeconds = $body['duration_seconds'] ?? null;
$statusClassvalId = $body['status_classval_id'] ?? null;
$notes = $body['notes'] ?? null;
$sourceDocument = $body['source_document'] ?? null;

if (!is_string($teamId) || trim($teamId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'team_id must be a non-empty string',
    ]);
}

if (!is_int($yearValue) || $yearValue < 1) {
    respond(400, [
        'status' => 'error',
        'error' => 'year_value must be a positive integer',
    ]);
}

if (!is_string($routineName) || trim($routineName) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'routine_name must be a non-empty string',
    ]);
}

if (!is_string($choreographyTypeId) || trim($choreographyTypeId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'choreography_type_id must be a non-empty string',
    ]);
}

if ($musicTitle !== null && !is_string($musicTitle)) {
    respond(400, [
        'status' => 'error',
        'error' => 'music_title must be a string when supplied',
    ]);
}

if ($durationSeconds !== null && (!is_int($durationSeconds) || $durationSeconds < 1)) {
    respond(400, [
        'status' => 'error',
        'error' => 'duration_seconds must be a positive integer when supplied',
    ]);
}

if ($statusClassvalId !== null && !is_string($statusClassvalId)) {
    respond(400, [
        'status' => 'error',
        'error' => 'status_classval_id must be a string when supplied',
    ]);
}

if ($notes !== null && !is_string($notes)) {
    respond(400, [
        'status' => 'error',
        'error' => 'notes must be a string when supplied',
    ]);
}

if ($sourceDocument !== null && !is_string($sourceDocument)) {
    respond(400, [
        'status' => 'error',
        'error' => 'source_document must be a string when supplied',
    ]);
}

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $result = create_performance_routine(
        $pdo,
        trim($teamId),
        $yearValue,
        trim($routineName),
        trim($choreographyTypeId),
        $musicTitle,
        $durationSeconds,
        $statusClassvalId,
        $notes,
        $sourceDocument
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'result' => $result,
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
        'error' => 'Failed to create performance routine',
        'database' => $expectedDatabase,
    ], $e);
}