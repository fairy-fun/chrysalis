<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$projectionId = isset($_GET['projection_id'])
    ? (int)$_GET['projection_id']
    : 0;

$weekIndex = isset($_GET['week_index'])
    ? (int)$_GET['week_index']
    : 0;

if ($projectionId <= 0) {
    respond(400, [
        'status' => 'error',
        'error' => 'projection_id must be a positive integer',
    ]);
}

if ($weekIndex <= 0) {
    respond(400, [
        'status' => 'error',
        'error' => 'week_index must be a positive integer',
    ]);
}

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {

    /*
    |--------------------------------------------------------------------------
    | Resolve canonical week container
    |--------------------------------------------------------------------------
    */

    $weekStmt = $pdo->prepare("
        SELECT
            w.id,
            w.entity_id,
            w.projection_id,
            w.week_index,
            w.summary,
            w.notes
        FROM calendar_book_weeks w
        WHERE w.projection_id = :projection_id
          AND w.week_index = :week_index
        LIMIT 1
    ");

    $weekStmt->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
    ]);

    $week = $weekStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($week)) {
        respond(404, [
            'status' => 'error',
            'error' => 'Week not found',
            'database' => $expectedDatabase,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve all days for week
    |--------------------------------------------------------------------------
    */

    $daysStmt = $pdo->prepare("
        SELECT
            d.id,
            d.entity_id,
            d.projection_id,
            d.week_id,
            d.day_index,
            d.summary,
            d.notes
        FROM calendar_book_days d
        WHERE d.projection_id = :projection_id
          AND d.week_id = :week_id
        ORDER BY d.day_index ASC
    ");

    $daysStmt->execute([
        ':projection_id' => $projectionId,
        ':week_id' => (int)$week['id'],
    ]);

    $days = $daysStmt->fetchAll(PDO::FETCH_ASSOC);

    $weekTree = [
        'week' => $week,
        'days' => [],
    ];

    if (!$days) {
        respond(200, [
            'status' => 'ok',
            'database' => $expectedDatabase,
            'data' => $weekTree,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare reusable statements
    |--------------------------------------------------------------------------
    */

    $timesStmt = $pdo->prepare("
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
            cv.label AS classval_label,

            COALESCE(
                NULLIF(TRIM(cv.label), ''),
                NULLIF(TRIM(t.summary), ''),
                NULLIF(TRIM(t.notes), ''),
                CONCAT('Time ', t.time_index)
            ) AS display_label

        FROM calendar_book_times t

        LEFT JOIN calendar_time_label_classvals cv
            ON TRIM(cv.id) = TRIM(t.time_label_id)

        WHERE t.projection_id = :projection_id
          AND t.day_id = :day_id

        ORDER BY t.time_index ASC
    ");

    $eventsStmt = $pdo->prepare("
        SELECT
            e.id,
            e.entity_id,
            e.projection_id,
            e.book_time_id,
            e.event_index,
            e.subevent_index,
            e.sequence_index,
            e.summary,
            e.notes,

            pp.id AS prose_projection_id,
            pp.published_prose_draft_id,

            pd.prose_body

        FROM calendar_events e

        LEFT JOIN (
            SELECT
                p1.target_entity_id,
                p1.id,
                p1.published_prose_draft_id
            FROM prose_projections p1
            INNER JOIN (
                SELECT
                    target_entity_id,
                    MIN(projection_order) AS resolved_projection_order
                FROM prose_projections
                WHERE published_prose_draft_id IS NOT NULL
                  AND projection_order IS NOT NULL
                  AND role_id = 'prose_projection_role_primary'
                  AND projection_type_id = 'projection_type_timeline_view'
                  AND is_export_target = 1
                GROUP BY target_entity_id
            ) resolved
                ON resolved.target_entity_id = p1.target_entity_id
               AND resolved.resolved_projection_order = p1.projection_order
            WHERE p1.published_prose_draft_id IS NOT NULL
              AND p1.role_id = 'prose_projection_role_primary'
              AND p1.projection_type_id = 'projection_type_timeline_view'
              AND p1.is_export_target = 1
        ) pp
            ON pp.target_entity_id = e.entity_id

        LEFT JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id

        WHERE e.projection_id = :projection_id
          AND e.book_time_id = :book_time_id

        ORDER BY
            e.event_index ASC,
            e.subevent_index ASC,
            e.sequence_index ASC,
            e.id ASC
    ");

    /*
    |--------------------------------------------------------------------------
    | Build canonical render tree
    |--------------------------------------------------------------------------
    */

    foreach ($days as $day) {

        $timesStmt->execute([
            ':projection_id' => $projectionId,
            ':day_id' => (int)$day['id'],
        ]);

        $times = $timesStmt->fetchAll(PDO::FETCH_ASSOC);

        $hydratedTimes = [];

        foreach ($times as $time) {

            $eventsStmt->execute([
                ':projection_id' => $projectionId,
                ':book_time_id' => (int)$time['id'],
            ]);

            $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

            $hydratedTimes[] = [
                'id' => (int)$time['id'],
                'entity_id' => $time['entity_id'],
                'time_index' => (int)$time['time_index'],
                'display_label' => $time['display_label'],
                'summary' => $time['summary'],
                'notes' => $time['notes'],
                'events' => array_map(
                    static function (array $event): array {

                        return [
                            'id' => (int)$event['id'],
                            'entity_id' => $event['entity_id'],

                            'projection_id'
                                => (int)$event['projection_id'],

                            'book_time_id'
                                => (int)$event['book_time_id'],

                            'event_index'
                                => $event['event_index'] !== null
                                    ? (int)$event['event_index']
                                    : null,

                            'subevent_index'
                                => $event['subevent_index'] !== null
                                    ? (int)$event['subevent_index']
                                    : null,

                            'sequence_index'
                                => $event['sequence_index'] !== null
                                    ? (int)$event['sequence_index']
                                    : null,

                            'summary' => $event['summary'],
                            'notes' => $event['notes'],

                            'prose_projection_id'
                                => $event['prose_projection_id'] !== null
                                    ? (int)$event['prose_projection_id']
                                    : null,

                            'published_prose_draft_id'
                                => $event['published_prose_draft_id'] !== null
                                    ? (int)$event['published_prose_draft_id']
                                    : null,

                            'prose_body' => $event['prose_body'],
                        ];
                    },
                    $events
                ),
            ];
        }

        $weekTree['days'][] = [
            'id' => (int)$day['id'],
            'entity_id' => $day['entity_id'],
            'day_index' => (int)$day['day_index'],
            'summary' => $day['summary'],
            'notes' => $day['notes'],
            'times' => $hydratedTimes,
        ];
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,

        'canonical' => [
            'projection_id' => $projectionId,
            'week_index' => $weekIndex,
        ],

        'data' => $weekTree,
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to resolve week prose view',
        'database' => $expectedDatabase,
    ], $e);
}
