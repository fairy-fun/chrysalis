<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/bootstrap.php';

function fw_chill_extract_book_week_day_request(string $message): ?array
{
    $bookNumber = null;
    $weekIndex = null;
    $dayIndex = null;

    if (preg_match('/\bbook\s+(\d+)\b/i', $message, $matches) === 1) {
        $bookNumber = (int) $matches[1];
    }

    if (preg_match('/\bweek\s+(\d+)\b/i', $message, $matches) === 1) {
        $weekIndex = (int) $matches[1];
    }

    if (preg_match('/\bday\s+(\d+)\b/i', $message, $matches) === 1) {
        $dayIndex = (int) $matches[1];
    } else {
        $dayNames = [
            'sunday' => 1,
            'monday' => 2,
            'tuesday' => 3,
            'wednesday' => 4,
            'thursday' => 5,
            'friday' => 6,
            'saturday' => 7,
        ];

        foreach ($dayNames as $name => $index) {
            if (preg_match('/\b' . preg_quote($name, '/') . '\b/i', $message) === 1) {
                $dayIndex = $index;
                break;
            }
        }
    }

    if ($bookNumber === null || $weekIndex === null || $dayIndex === null) {
        return null;
    }

    if ($bookNumber < 1 || $weekIndex < 1 || $dayIndex < 1) {
        return null;
    }

    return [
        'book_number' => $bookNumber,
        'week_index' => $weekIndex,
        'day_index' => $dayIndex,
    ];
}

function fw_chill_maybe_answer_calendar_time_layers(
    PDO $pdo,
    string $message
): ?array {
    $lowerMessage = mb_strtolower(trim($message));

    if (
        !str_contains($lowerMessage, 'time layer')
        && !str_contains($lowerMessage, 'time slot')
        && !str_contains($lowerMessage, 'time layers')
        && !str_contains($lowerMessage, 'time slots')
    ) {
        return null;
    }

    if (
        !str_contains($lowerMessage, 'exist')
        && !str_contains($lowerMessage, 'already')
        && !str_contains($lowerMessage, 'available')
        && !str_contains($lowerMessage, 'current')
    ) {
        return null;
    }

    $locality = fw_chill_extract_book_week_day_request($message);

    if ($locality === null) {
        return null;
    }

    $projectionCode = sprintf(
        'book_projection_BOOK-%03d',
        (int) $locality['book_number']
    );

    $projectionStmt = $pdo->prepare(
        '
        SELECT
            id,
            projection_code
        FROM calendar_projections
        WHERE projection_code = :projection_code
        LIMIT 1
        '
    );

    $projectionStmt->execute([
        ':projection_code' => $projectionCode,
    ]);

    $projection = $projectionStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($projection)) {
        return [
            'status' => 'not_found',
            'message' => 'Book projection not found.',
            'locality' => $locality,
        ];
    }

    $weekStmt = $pdo->prepare(
        '
        SELECT
            id,
            week_index
        FROM calendar_book_weeks
        WHERE projection_id = :projection_id
          AND week_index = :week_index
        LIMIT 1
        '
    );

    $weekStmt->execute([
        ':projection_id' => (int) $projection['id'],
        ':week_index' => (int) $locality['week_index'],
    ]);

    $week = $weekStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($week)) {
        return [
            'status' => 'not_found',
            'message' => 'Book week not found.',
            'locality' => $locality,
        ];
    }

    $dayStmt = $pdo->prepare(
        '
        SELECT
            id,
            day_index
        FROM calendar_book_days
        WHERE projection_id = :projection_id
          AND week_id = :week_id
          AND day_index = :day_index
        LIMIT 1
        '
    );

    $dayStmt->execute([
        ':projection_id' => (int) $projection['id'],
        ':week_id' => (int) $week['id'],
        ':day_index' => (int) $locality['day_index'],
    ]);

    $day = $dayStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($day)) {
        return [
            'status' => 'not_found',
            'message' => 'Book day not found.',
            'locality' => $locality,
        ];
    }

    $timeStmt = $pdo->prepare(
        '
        SELECT
            t.id,
            t.entity_id,
            t.projection_id,
            t.day_id,
            t.time_index,
            t.summary,
            t.notes,
            t.time_label_id,
            cv.label AS time_label,
            COALESCE(
                NULLIF(TRIM(cv.label), \'\'),
                NULLIF(TRIM(t.summary), \'\'),
                NULLIF(TRIM(t.notes), \'\'),
                CONCAT(\'Time \', t.time_index)
            ) AS display_label
        FROM calendar_book_times t
        LEFT JOIN calendar_time_label_classvals cv
            ON cv.id = t.time_label_id
        WHERE t.projection_id = :projection_id
          AND t.day_id = :day_id
        ORDER BY t.time_index ASC
        '
    );

    $timeStmt->execute([
        ':projection_id' => (int) $projection['id'],
        ':day_id' => (int) $day['id'],
    ]);

    $rows = $timeStmt->fetchAll(PDO::FETCH_ASSOC);

    $timeLayers = [];
    $renderedLabels = [];

    foreach ($rows as $row) {
        $timeIndex = (int) $row['time_index'];
        $displayLabel = trim((string) ($row['display_label'] ?? ''));

        if ($displayLabel === '') {
            $displayLabel = 'Time ' . $timeIndex;
        }

        $renderLabel = $displayLabel === ('Time ' . $timeIndex)
            ? $displayLabel
            : sprintf('Time %d — %s', $timeIndex, $displayLabel);

        $timeLayers[] = array_merge(
            $row,
            [
                'time_index' => $timeIndex,
                'display_label' => $displayLabel,
                'render_label' => $renderLabel,
            ]
        );

        $renderedLabels[] = $renderLabel;
    }

    $messageLines = [];

    if ($renderedLabels === []) {
        $messageLines[] = sprintf(
            'For Book %d → Week %d → Day %d, no time layers currently exist.',
            (int) $locality['book_number'],
            (int) $locality['week_index'],
            (int) $locality['day_index']
        );
    } else {
        $messageLines[] = sprintf(
            'For Book %d → Week %d → Day %d, the existing time layers are:',
            (int) $locality['book_number'],
            (int) $locality['week_index'],
            (int) $locality['day_index']
        );

        foreach ($renderedLabels as $label) {
            $messageLines[] = $label;
        }
    }

    return [
        'status' => 'ok',
        'operation' => 'calendar_time_layers_read',
        'message' => implode("\n", $messageLines),
        'locality' => [
            'book_number' => (int) $locality['book_number'],
            'projection_id' => (int) $projection['id'],
            'projection_code' => (string) $projection['projection_code'],
            'week_id' => (int) $week['id'],
            'week_index' => (int) $week['week_index'],
            'day_id' => (int) $day['id'],
            'day_index' => (int) $day['day_index'],
        ],
        'time_layers' => $timeLayers,
        'rendered_labels' => $renderedLabels,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$userMessage = $body['message'] ?? null;
$sessionId = $body['session_id'] ?? null;

if (!is_string($userMessage) || trim($userMessage) === '') {

    respond(400, [
        'status' => 'error',
        'error' => 'message must be a non-empty string',
    ]);
}

if ($sessionId !== null && !is_string($sessionId)) {

    respond(400, [
        'status' => 'error',
        'error' => 'session_id must be a string when provided',
    ]);
}

$pdo = makePdo('write');

try {
    $timeLayerResponse = fw_chill_maybe_answer_calendar_time_layers(
        $pdo,
        trim($userMessage)
    );

    if (is_array($timeLayerResponse)) {
        respond(200, $timeLayerResponse);
    }

    $result = fw_start_chat_request(
        $pdo,
        trim($userMessage),
        is_string($sessionId) ? trim($sessionId) : null
    );

    respond(200, $result);
} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to start or continue workflow chat',
    ], $e);
}