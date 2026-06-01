<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

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
    $projectionStmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            projection_code,
            projection_title,
            projection_type_id
        FROM sxnzlfun_chrysalis.calendar_projections
        WHERE id = :projection_id
        LIMIT 1
    ");

    $projectionStmt->execute([
        ':projection_id' => $projectionId,
    ]);

    $projection = $projectionStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($projection)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Projection not found',
            'database' => $expectedDatabase,
        ]);
    }

    $eventsStmt = $pdo->prepare("
        SELECT
            ce.entity_id,
            parent.entity_id AS parent_entity_id,
            ce.projection_id,
            ce.layer_id,
            ce.sequence_index,
            ce.event_index,
            ce.chronology_address,
            ce.summary,
            ce.notes,
            ce.real_date_start_id,
            start_date_lookup.date_value AS real_date_start,
            ce.real_date_end_id,
            end_date_lookup.date_value AS real_date_end,
            ce.created_at,
            ce.updated_at,
            EXISTS(
                SELECT 1
                FROM sxnzlfun_chrysalis.prose_projections pp
                INNER JOIN sxnzlfun_chrysalis.prose_drafts pd
                    ON pd.id = pp.published_prose_draft_id
                WHERE pp.target_entity_id = ce.entity_id
                  AND TRIM(COALESCE(pd.prose_body, '')) <> ''
            ) AS has_prose
        FROM sxnzlfun_chrysalis.calendar_events ce
        LEFT JOIN sxnzlfun_chrysalis.calendar_events parent
            ON parent.id = ce.parent_event_id
           AND parent.projection_id = ce.projection_id
        INNER JOIN sxnzlfun_chrysalis.dates start_date_lookup
            ON start_date_lookup.id = ce.real_date_start_id
        LEFT JOIN sxnzlfun_chrysalis.dates end_date_lookup
            ON end_date_lookup.id = ce.real_date_end_id
        WHERE ce.projection_id = :projection_id
          AND ce.layer_id = 'calendar_layer_event'
          AND start_date_lookup.date_value <= :end_date
          AND COALESCE(end_date_lookup.date_value, start_date_lookup.date_value) >= :start_date
        ORDER BY
            start_date_lookup.date_value ASC,
            COALESCE(end_date_lookup.date_value, start_date_lookup.date_value) ASC,
            ce.sequence_index ASC,
            ce.entity_id ASC
    ");

    $eventsStmt->execute([
        ':projection_id' => $projectionId,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as &$event) {
        $event['projection_id'] = (int) $event['projection_id'];
        $event['sequence_index'] = $event['sequence_index'] === null
            ? null
            : (int) $event['sequence_index'];
        $event['event_index'] = $event['event_index'] === null
            ? null
            : (int) $event['event_index'];
        $event['has_prose'] = (bool) $event['has_prose'];
    }
    unset($event);

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
