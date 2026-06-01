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

function fw_chill_parse_projection_span_date(mixed $value, string $field): string
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

function fw_chill_extract_projection_span_request(string $message): ?array
{
    if (
        preg_match(
            '/\b(?:calendar_)?projection_id\s*=\s*(\d+)\b/i',
            $message,
            $matches
        ) !== 1
    ) {
        return null;
    }

    $projectionId = (int) $matches[1];

    if ($projectionId <= 0) {
        return null;
    }

    preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $message, $dateMatches);

    return [
        'projection_id' => $projectionId,
        'dates' => array_values(array_unique($dateMatches[0] ?? [])),
    ];
}

function fw_chill_projection_span_prompt(int $projectionId, string $field): string
{
    if ($field === 'end_date') {
        return sprintf(
            'What end_date should I use for projection_id = %d? Reply in YYYY-MM-DD.',
            $projectionId
        );
    }

    return sprintf(
        'What start_date should I use for projection_id = %d? Reply in YYYY-MM-DD.',
        $projectionId
    );
}

function fw_chill_projection_span_event_line(array $event): string
{
    $start = (string) ($event['real_date_start'] ?? 'unknown');
    $end = (string) ($event['real_date_end'] ?? '');
    $summary = trim((string) ($event['summary'] ?? ''));
    $entityId = trim((string) ($event['entity_id'] ?? ''));

    if ($summary === '') {
        $summary = $entityId !== '' ? $entityId : 'Untitled event';
    }

    $dateLabel = $start;

    if ($end !== '' && $end !== $start) {
        $dateLabel .= ' to ' . $end;
    }

    if ($entityId !== '') {
        return sprintf('%s - %s (%s)', $dateLabel, $summary, $entityId);
    }

    return sprintf('%s - %s', $dateLabel, $summary);
}

function fw_chill_build_projection_span_answer(
    int $projectionId,
    string $startDate,
    string $endDate,
    array $events
): string {
    $count = count($events);

    if ($count === 0) {
        return sprintf(
            'Projection %d has no calendar events between %s and %s.',
            $projectionId,
            $startDate,
            $endDate
        );
    }

    $lines = [
        sprintf(
            'Projection %d has %d calendar event%s between %s and %s:',
            $projectionId,
            $count,
            $count === 1 ? '' : 's',
            $startDate,
            $endDate
        ),
    ];

    $limit = min($count, 25);

    for ($i = 0; $i < $limit; $i++) {
        $lines[] = '- ' . fw_chill_projection_span_event_line($events[$i]);
    }

    if ($count > $limit) {
        $lines[] = sprintf('Showing the first %d events in the text summary.', $limit);
    }

    return implode("\n", $lines);
}

function fw_chill_fetch_projection_span_read(
    PDO $pdo,
    int $projectionId,
    string $startDate,
    string $endDate
): array {
    $expectedDatabase = verifyExpectedDatabase($pdo);

    $projectionStmt = $pdo->prepare(
        "
        SELECT
            id,
            entity_id,
            projection_code,
            projection_title,
            projection_type_id
        FROM sxnzlfun_chrysalis.calendar_projections
        WHERE id = :projection_id
        LIMIT 1
        "
    );

    $projectionStmt->execute([
        ':projection_id' => $projectionId,
    ]);

    $projection = $projectionStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($projection)) {
        $message = sprintf('Projection %d was not found.', $projectionId);

        return [
            'status' => 'error',
            'operation' => 'getCalendarEventsForProjectionSpan',
            'message' => $message,
            'answer' => $message,
            'database' => $expectedDatabase,
            'calendar_projection_id' => $projectionId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'projection' => null,
            'events' => [],
            'is_informational_read' => true,
            'do_not_resume_workflow_from_this_response' => true,
            'next_user_action_required' => false,
        ];
    }

    $eventsStmt = $pdo->prepare(
        "
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
        "
    );

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

    $answer = fw_chill_build_projection_span_answer(
        $projectionId,
        $startDate,
        $endDate,
        $events
    );

    return [
        'status' => 'ok',
        'operation' => 'getCalendarEventsForProjectionSpan',
        'message' => $answer,
        'answer' => $answer,
        'database' => $expectedDatabase,
        'calendar_projection_id' => $projectionId,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'projection' => $projection,
        'events' => $events,
        'is_informational_read' => true,
        'do_not_resume_workflow_from_this_response' => true,
        'next_user_action_required' => false,
    ];
}

function fw_chill_open_projection_span_session(
    PDO $pdo,
    int $projectionId,
    ?string $startDate = null
): array {
    $sessionId = fw_generate_workflow_session_id();
    $expectedInput = $startDate === null ? 'start_date' : 'end_date';
    $stateId = $startDate === null ? 'awaiting_start_date' : 'awaiting_end_date';
    $context = [
        'projection_id' => $projectionId,
    ];
    $snapshots = [
        [
            'accepted_input' => [
                'projection_id' => $projectionId,
            ],
        ],
    ];

    if ($startDate !== null) {
        $context['start_date'] = $startDate;
        $snapshots[] = [
            'accepted_input' => [
                'start_date' => $startDate,
            ],
        ];
    }

    fw_store_workflow_session(
        $pdo,
        $sessionId,
        'calendar_projection_span_read',
        $stateId,
        'active',
        $expectedInput,
        $context,
        $snapshots
    );

    $message = fw_chill_projection_span_prompt($projectionId, $expectedInput);

    return [
        'status' => 'input_required',
        'operation' => 'getCalendarEventsForProjectionSpan',
        'message' => $message,
        'answer' => $message,
        'session_id' => $sessionId,
        'expected_input' => $expectedInput,
        'calendar_projection_id' => $projectionId,
        'next_user_action_required' => true,
        'recommended_reply_options' => [],
    ];
}

function fw_chill_resume_projection_span_session(
    PDO $pdo,
    string $userMessage,
    array $session
): array {
    $sessionId = (string) ($session['session_id'] ?? '');
    $expectedInput = (string) ($session['expected_input'] ?? '');
    $context = is_array($session['context'] ?? null)
        ? $session['context']
        : [];
    $snapshots = is_array($session['snapshots'] ?? null)
        ? $session['snapshots']
        : [];
    $projectionId = (int) ($context['projection_id'] ?? 0);

    if ($projectionId <= 0) {
        return [
            'status' => 'error',
            'message' => 'Projection interview session is missing projection_id.',
            'answer' => 'Projection interview session is missing projection_id.',
            'session_id' => $sessionId,
        ];
    }

    if ($expectedInput === 'start_date') {
        try {
            $startDate = fw_chill_parse_projection_span_date($userMessage, 'start_date');
        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'input_required',
                'operation' => 'getCalendarEventsForProjectionSpan',
                'message' => $e->getMessage() . ' Reply with start_date in YYYY-MM-DD.',
                'answer' => $e->getMessage() . ' Reply with start_date in YYYY-MM-DD.',
                'session_id' => $sessionId,
                'expected_input' => 'start_date',
                'calendar_projection_id' => $projectionId,
                'next_user_action_required' => true,
                'recommended_reply_options' => [],
            ];
        }

        $context['start_date'] = $startDate;
        $snapshots[] = [
            'accepted_input' => [
                'start_date' => $startDate,
            ],
        ];

        fw_store_workflow_session(
            $pdo,
            $sessionId,
            'calendar_projection_span_read',
            'awaiting_end_date',
            'active',
            'end_date',
            $context,
            $snapshots
        );

        $message = fw_chill_projection_span_prompt($projectionId, 'end_date');

        return [
            'status' => 'input_required',
            'operation' => 'getCalendarEventsForProjectionSpan',
            'message' => $message,
            'answer' => $message,
            'session_id' => $sessionId,
            'expected_input' => 'end_date',
            'calendar_projection_id' => $projectionId,
            'start_date' => $startDate,
            'next_user_action_required' => true,
            'recommended_reply_options' => [],
        ];
    }

    if ($expectedInput === 'end_date') {
        try {
            $endDate = fw_chill_parse_projection_span_date($userMessage, 'end_date');
        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'input_required',
                'operation' => 'getCalendarEventsForProjectionSpan',
                'message' => $e->getMessage() . ' Reply with end_date in YYYY-MM-DD.',
                'answer' => $e->getMessage() . ' Reply with end_date in YYYY-MM-DD.',
                'session_id' => $sessionId,
                'expected_input' => 'end_date',
                'calendar_projection_id' => $projectionId,
                'start_date' => $context['start_date'] ?? null,
                'next_user_action_required' => true,
                'recommended_reply_options' => [],
            ];
        }

        $startDate = $context['start_date'] ?? null;

        if (!is_string($startDate) || trim($startDate) === '') {
            return [
                'status' => 'error',
                'message' => 'Projection interview session is missing start_date.',
                'answer' => 'Projection interview session is missing start_date.',
                'session_id' => $sessionId,
                'calendar_projection_id' => $projectionId,
            ];
        }

        if ($startDate > $endDate) {
            $message = 'end_date must be greater than or equal to start_date. Reply with end_date in YYYY-MM-DD.';

            return [
                'status' => 'input_required',
                'operation' => 'getCalendarEventsForProjectionSpan',
                'message' => $message,
                'answer' => $message,
                'session_id' => $sessionId,
                'expected_input' => 'end_date',
                'calendar_projection_id' => $projectionId,
                'start_date' => $startDate,
                'next_user_action_required' => true,
                'recommended_reply_options' => [],
            ];
        }

        $context['end_date'] = $endDate;
        $snapshots[] = [
            'accepted_input' => [
                'end_date' => $endDate,
            ],
        ];

        $result = fw_chill_fetch_projection_span_read(
            $pdo,
            $projectionId,
            $startDate,
            $endDate
        );

        fw_store_workflow_session(
            $pdo,
            $sessionId,
            'calendar_projection_span_read',
            'completed',
            'complete',
            null,
            $context,
            $snapshots
        );

        $result['session_id'] = $sessionId;

        return $result;
    }

    return [
        'status' => 'error',
        'message' => 'Projection interview session is not awaiting input.',
        'answer' => 'Projection interview session is not awaiting input.',
        'session_id' => $sessionId,
        'calendar_projection_id' => $projectionId,
    ];
}

function fw_chill_maybe_answer_projection_span_read(
    PDO $pdo,
    string $message,
    ?array $session = null
): ?array {
    if (
        is_array($session)
        && ($session['workflow_id'] ?? null) === 'calendar_projection_span_read'
    ) {
        return fw_chill_resume_projection_span_session($pdo, trim($message), $session);
    }

    $request = fw_chill_extract_projection_span_request($message);

    if ($request === null) {
        return null;
    }

    $projectionId = (int) $request['projection_id'];
    $dates = is_array($request['dates'] ?? null) ? $request['dates'] : [];

    if (count($dates) >= 2) {
        $startDate = fw_chill_parse_projection_span_date($dates[0], 'start_date');
        $endDate = fw_chill_parse_projection_span_date($dates[1], 'end_date');

        if ($startDate > $endDate) {
            throw new InvalidArgumentException('start_date must be less than or equal to end_date');
        }

        return fw_chill_fetch_projection_span_read(
            $pdo,
            $projectionId,
            $startDate,
            $endDate
        );
    }

    if (count($dates) === 1) {
        $startDate = fw_chill_parse_projection_span_date($dates[0], 'start_date');
        return fw_chill_open_projection_span_session($pdo, $projectionId, $startDate);
    }

    return fw_chill_open_projection_span_session($pdo, $projectionId);
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
    $trimmedUserMessage = trim($userMessage);

    if (is_string($sessionId) && trim($sessionId) !== '') {
        $session = fw_load_workflow_session(
            $pdo,
            trim($sessionId)
        );
    }

    $projectionSpanResponse = fw_chill_maybe_answer_projection_span_read(
        $pdo,
        $trimmedUserMessage,
        $session
    );

    if (is_array($projectionSpanResponse)) {
        respond(200, $projectionSpanResponse);
    }

    $timeLayerResponse = fw_chill_maybe_answer_calendar_time_layers(
        $pdo,
        $trimmedUserMessage,
        $session
    );

    if (is_array($timeLayerResponse)) {
        respond(200, $timeLayerResponse);
    }

    $result = fw_start_chat_request(
        $pdo,
        $trimmedUserMessage,
        is_string($sessionId) ? trim($sessionId) : null
    );

    respond(200, $result);
} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to start or continue workflow chat',
    ], $e);
}
