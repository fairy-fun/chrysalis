<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';

require_once __DIR__
    . '/../calendar/admin/calendar_book_chronology_materializer.php';

require_once __DIR__
    . '/../calendar/calendar_projection_materializer.php';

function fw_execute_workflow_calendar_book_time_create(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    /*
    |--------------------------------------------------------------------------
    | Resolve payload
    |--------------------------------------------------------------------------
    |
    | This workflow is Tier 0/admin chronology authoring.
    |
    | It materializes canonical chronology containers:
    |
    |   calendar_book_times
    |
    | It must NOT create calendar_events.
    |
    */

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $projectionId = (int)($payload['projection_id'] ?? 0);

    $weekId = (int)($payload['week_id'] ?? 0);

    $dayId = (int)($payload['day_id'] ?? 0);

    $weekIndex = (int)($payload['week_index'] ?? 0);

    $dayIndex = (int)($payload['day_index'] ?? 0);

    $timeIndex = (int)($payload['time_index'] ?? 0);

    $timeLabelId = array_key_exists('time_label_id', $payload)
        ? trim((string)$payload['time_label_id'])
        : null;

    $summary = array_key_exists('summary', $payload)
        ? trim((string)$payload['summary'])
        : null;

    $notes = array_key_exists('notes', $payload)
        ? trim((string)$payload['notes'])
        : null;

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($projectionId < 1) {

        throw new RuntimeException(
            'calendar_book_time_create requires projection_id'
        );
    }

    if ($weekId < 1) {

        throw new RuntimeException(
            'calendar_book_time_create requires week_id'
        );
    }

    if ($dayId < 1) {

        throw new RuntimeException(
            'calendar_book_time_create requires day_id'
        );
    }

    if ($weekIndex < 1) {

        throw new RuntimeException(
            'calendar_book_time_create requires positive week_index'
        );
    }

    if ($dayIndex < 1) {

        throw new RuntimeException(
            'calendar_book_time_create requires positive day_index'
        );
    }

    if ($timeIndex < 1) {

        throw new RuntimeException(
            'calendar_book_time_create requires positive time_index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical parent week containment validation
    |--------------------------------------------------------------------------
    |
    | Time creation requires an existing canonical Book week container.
    |
    | Tier 0 may materialize times.
    | Tier 0 must NOT infer or create missing weeks here.
    |
    */

    $parentWeekStmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            projection_id,
            week_index
        FROM calendar_book_weeks
        WHERE id = :week_id
          AND projection_id = :projection_id
          AND week_index = :week_index
        LIMIT 1
    ");

    $parentWeekStmt->execute([
        ':week_id' => $weekId,
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
    ]);

    $parentWeek = $parentWeekStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($parentWeek)) {

        throw new RuntimeException(
            'Canonical parent Book week does not exist'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical parent day containment validation
    |--------------------------------------------------------------------------
    |
    | Time creation requires an existing canonical Book day container.
    |
    | Tier 0 must NOT infer or create missing days here.
    |
    */

    $parentDayStmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            projection_id,
            week_id,
            day_index,
            day_of_week_id
        FROM calendar_book_days
        WHERE id = :day_id
          AND projection_id = :projection_id
          AND week_id = :week_id
          AND day_index = :day_index
        LIMIT 1
    ");

    $parentDayStmt->execute([
        ':day_id' => $dayId,
        ':projection_id' => $projectionId,
        ':week_id' => $weekId,
        ':day_index' => $dayIndex,
    ]);

    $parentDay = $parentDayStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($parentDay)) {

        throw new RuntimeException(
            'Canonical parent Book day does not exist'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Existing chronology protection
    |--------------------------------------------------------------------------
    |
    | Duplicate canonical time locality is forbidden.
    |
    | Canonical locality:
    |
    |   projection_id + day_id + time_index
    |
    */

    $existingStmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            projection_id,
            day_id,
            time_index
        FROM calendar_book_times
        WHERE projection_id = :projection_id
          AND day_id = :day_id
          AND time_index = :time_index
        LIMIT 1
    ");

    $existingStmt->execute([
        ':projection_id' => $projectionId,
        ':day_id' => $dayId,
        ':time_index' => $timeIndex,
    ]);

    $existingTime = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($existingTime)) {

        throw new RuntimeException(
            'Canonical Book time already exists'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical chronology container materialization
    |--------------------------------------------------------------------------
    */

    $time = materialize_calendar_book_time(
        $pdo,
        $projectionId,
        $dayId,
        $timeIndex
    );

    /*
    |--------------------------------------------------------------------------
    | Optional metadata enrichment
    |--------------------------------------------------------------------------
    |
    | chronology locality remains:
    |
    |   projection_id + day_id + time_index
    |
    | These fields are descriptive only.
    |
    */

    $updateFields = [];

    $updateParams = [
        ':id' => (int)$time['id'],
    ];

    if ($summary !== null && $summary !== '') {

        $updateFields[] = 'summary = :summary';

        $updateParams[':summary'] = $summary;
    }

    if ($notes !== null && $notes !== '') {

        $updateFields[] = 'notes = :notes';

        $updateParams[':notes'] = $notes;
    }

    if ($timeLabelId !== null && $timeLabelId !== '') {

        $updateFields[] = 'time_label_id = :time_label_id';

        $updateParams[':time_label_id'] = $timeLabelId;
    }

    if ($updateFields !== []) {

        $updateFields[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare(
            '
            UPDATE sxnzlfun_chrysalis.calendar_book_times
            SET ' . implode(",\n                ", $updateFields) . '
            WHERE id = :id
            '
        );

        $stmt->execute($updateParams);

        $reload = $pdo->prepare("
            SELECT *
            FROM sxnzlfun_chrysalis.calendar_book_times
            WHERE id = :id
            LIMIT 1
        ");

        $reload->execute([
            ':id' => (int)$time['id'],
        ]);

        $reloaded = $reload->fetch(PDO::FETCH_ASSOC);

        if (is_array($reloaded)) {
            $time = $reloaded;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Projection rebuild
    |--------------------------------------------------------------------------
    |
    | Chronology containers affect projection render topology.
    |
    */

    $projectionBuildId = rebuild_calendar_projection(
        $pdo,
        $projectionId
    );

    return [

        'success' => true,

        'context' => array_merge(
            $context,
            [

                'calendar_book_time' => $time,

                'calendar_book_time_create' => [

                    'projection_id' => $projectionId,

                    'week_id' => $weekId,

                    'day_id' => $dayId,

                    'week_index' => $weekIndex,

                    'day_index' => $dayIndex,

                    'time_index' => $timeIndex,

                    'calendar_book_time_id'
                        => (int)$time['id'],

                    'entity_id'
                        => $time['entity_id'] ?? null,

                    'projection_build_id'
                        => $projectionBuildId,
                ],

                'projection_build_id' => $projectionBuildId,
            ]
        ),
    ];
}