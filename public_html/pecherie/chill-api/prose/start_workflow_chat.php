<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/bootstrap.php';

function fw_chill_parse_book_number(mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    if (preg_match('/\bbook\s+(\d+)\b/i', $value, $matches) === 1) {
        return (int) $matches[1];
    }

    if (preg_match('/\bBOOK-(\d{1,3})\b/i', $value, $matches) === 1) {
        return (int) $matches[1];
    }

    if (ctype_digit($value)) {
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    return null;
}

function fw_chill_parse_week_index(mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    if (preg_match('/\bweek\s+(\d+)\b/i', $value, $matches) === 1) {
        return (int) $matches[1];
    }

    if (ctype_digit($value)) {
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    return null;
}

function fw_chill_parse_day_index(mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    if (preg_match('/\bday\s+(\d+)\b/i', $value, $matches) === 1) {
        return (int) $matches[1];
    }

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
        if (preg_match('/\b' . preg_quote($name, '/') . '\b/i', $value) === 1) {
            return $index;
        }
    }

    if (ctype_digit($value)) {
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    return null;
}

function fw_chill_extract_book_week_day_request(
    string $message,
    ?array $session = null
): ?array {
    $bookNumber = fw_chill_parse_book_number($message);
    $weekIndex = fw_chill_parse_week_index($message);
    $dayIndex = fw_chill_parse_day_index($message);

    if ($session !== null) {
        $context = $session['context'] ?? [];

        if (is_array($context)) {
            if ($bookNumber === null) {
                $bookNumber = fw_chill_parse_book_number(
                    $context['calendar_normalized_input']['projection_code']
                    ?? $context['projection']['projection_code']
                    ?? $context['projection_id']
                    ?? null
                );
            }

            if ($weekIndex === null) {
                $weekIndex = fw_chill_parse_week_index(
                    $context['calendar_normalized_input']['week_index']
                    ?? $context['book_week']['week_index']
                    ?? null
                );
            }

            if ($dayIndex === null) {
                $dayIndex = fw_chill_parse_day_index(
                    $context['calendar_normalized_input']['day_index']
                    ?? $context['book_day']['day_index']
                    ?? null
                );
            }
        }

        $snapshots = $session['snapshots'] ?? [];

        if (is_array($snapshots)) {
            foreach ($snapshots as $snapshot) {
                if (!is_array($snapshot)) {
                    continue;
                }

                $acceptedInput = $snapshot['accepted_input'] ?? [];

                if (!is_array($acceptedInput)) {
                    continue;
                }

                if ($bookNumber === null && array_key_exists('projection_id', $acceptedInput)) {
                    $bookNumber = fw_chill_parse_book_number($acceptedInput['projection_id']);
                }

                if ($weekIndex === null && array_key_exists('week_index', $acceptedInput)) {
                    $weekIndex = fw_chill_parse_week_index($acceptedInput['week_index']);
                }

                if ($dayIndex === null && array_key_exists('day_index', $acceptedInput)) {
                    $dayIndex = fw_chill_parse_day_index($acceptedInput['day_index']);
                }
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
    string $message,
    ?array $session = null
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

    $locality = fw_chill_extract_book_week_day_request($message, $session);

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
            'answer' => 'Book projection not found.',
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
            'answer' => 'Book week not found.',
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
            'answer' => 'Book day not found.',
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
            cv.id AS matched_time_label_id,
            cv.label AS time_label,
            COALESCE(
                NULLIF(TRIM(cv.label), \'\'),
                NULLIF(TRIM(t.summary), \'\'),
                NULLIF(TRIM(t.notes), \'\'),
                CONCAT(\'Time \', t.time_index)
            ) AS display_label
        FROM calendar_book_times t
        LEFT JOIN calendar_time_label_classvals cv
            ON TRIM(cv.id) = TRIM(t.time_label_id)
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
    $replyOptions = [];

    foreach ($rows as $row) {
        $timeIndex = (int) $row['time_index'];
        $displayLabel = trim((string) ($row['display_label'] ?? ''));

        if ($displayLabel === '') {
            $displayLabel = 'Time ' . $timeIndex;
        }

        $renderLabel = $displayLabel === ('Time ' . $timeIndex)
            ? $displayLabel
            : sprintf('Time %d — %s', $timeIndex, $displayLabel);

        $replyOption = 'Time ' . $timeIndex;

        $timeLayers[] = array_merge(
            $row,
            [
                'time_index' => $timeIndex,
                'display_label' => $displayLabel,
                'render_label' => $renderLabel,
                'reply_option' => $replyOption,
            ]
        );

        $renderedLabels[] = $renderLabel;
        $replyOptions[] = $replyOption;
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

        $messageLines[] = '';
        $messageLines[] = 'To continue creating the event, send a separate reply such as: Time 1.';
    }

    $answer = implode("\n", $messageLines);

    return [
        'status' => 'ok',
        'operation' => 'calendar_time_layers_read',
        'answer' => $answer,
        'message' => $answer,
        'time_layer_labels' => $renderedLabels,
        'recommended_reply_options' => [],
        'is_informational_read' => true,
        'do_not_resume_workflow_from_this_response' => true,
        'next_user_action_required' => true,
        'display_instruction' => 'Render the values in time_layer_labels exactly; do not synthesize labels from time_index alone. This is an informational read, not a workflow input submission.',
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

function fw_chill_resolve_message_from_body(array $body): ?string
{
    foreach (['message', 'user_message', 'chat_message', 'prompt', 'input'] as $key) {
        $value = $body[$key] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    $details = $body['details'] ?? null;

    if (is_string($details) && trim($details) !== '') {
        return trim($details);
    }

    if (is_array($details)) {
        foreach (['message', 'user_message', 'chat_message', 'prompt', 'input', 'request'] as $key) {
            $value = $details[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
    }

    $request = $body['request'] ?? null;

    if (is_string($request) && trim($request) !== '') {
        return trim($request);
    }

    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$userMessage = fw_chill_resolve_message_from_body($body);
$sessionId = $body['session_id'] ?? null;

if (!is_string($userMessage) || trim($userMessage) === '') {

    respond(400, [
        'status' => 'error',
        'error' => 'message must be a non-empty string',
        'accepted_message_fields' => [
            'message',
            'user_message',
            'chat_message',
            'prompt',
            'input',
            'details',
            'details.message',
            'request',
        ],
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
    $session = null;

    if (is_string($sessionId) && trim($sessionId) !== '') {
        $session = fw_load_workflow_session(
            $pdo,
            trim($sessionId)
        );
    }

    $timeLayerResponse = fw_chill_maybe_answer_calendar_time_layers(
        $pdo,
        trim($userMessage),
        $session
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