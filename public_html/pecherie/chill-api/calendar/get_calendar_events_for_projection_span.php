<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_projection_span_reader.php';

function parse_projection_span_date(mixed $value, string $field): string
{
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException($field . ' must be a non-empty YYYY-MM-DD string');
    }

    $value = trim($value);

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        throw new InvalidArgumentException($field . ' must be formatted as YYYY-MM-DD');
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($field . ' must be a valid calendar date');
    }

    return $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = $_QUERY_BODY ?? [];
$projectionId = $body['calendar_projection_id'] ?? $body['projection_id'] ?? null;
$startDate = $body['start_date'] ?? null;
$endDate = $body['end_date'] ?? null;

if (!is_int($projectionId) && !(is_string($projectionId) && ctype_digit($projectionId))) {
    respond(400, [
        'status' => 'error',
        'error' => 'calendar_projection_id must be a positive integer',
    ]);
}

$projectionId = (int) $projectionId;

if ($projectionId <= 0) {
    respond(400, [
        'status' => 'error',
        'error' => 'Invalid calendar_projection_id',
    ]);
}

try {
    $startDate = parse_projection_span_date($startDate, 'start_date');
    $endDate = parse_projection_span_date($endDate, 'end_date');
} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
    ]);
}

if ($startDate > $endDate) {
    respond(400, [
        'status' => 'error',
        'error' => 'start_date must be less than or equal to end_date',
    ]);
}

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $projection = fetch_calendar_projection_by_id($pdo, $projectionId);

    if (!is_array($projection)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Projection not found',
            'database' => $expectedDatabase,
        ]);
    }

    $events = fetch_calendar_events_for_projection_span(
        $pdo,
        $projectionId,
        $startDate,
        $endDate
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'calendar_projection_id' => $projectionId,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'projection' => $projection,
        'events' => $events,
    ]);
} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to fetch calendar events for projection span',
        'database' => $expectedDatabase,
    ], $e);
}
