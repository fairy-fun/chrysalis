<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__ . '/workflow_sql_dropin_builder.php';

require_once __DIR__
    . '/../calendar/admin/calendar_book_chronology_materializer.php';

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
    | Tier 0 canonical chronology topology authoring only.
    |
    | This workflow materializes:
    |
    |   calendar_book_times
    |
    | It must NOT:
    |
    | - create calendar_events
    | - infer chronology topology
    | - reconstruct chronology locality
    | - use chronology_address as authority
    |
    | Canonical locality authority:
    |
    |   projection_id
    |   + day_id
    |   + time_index
    |
    | time_index is pure locality.
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

    $timeIndex = (int)($payload['time_index'] ?? 0);

    $timeLabelId = array_key_exists('time_label_id', $payload)
        ? trim((string)$payload['time_label_id'])
        : null;

    if ($timeLabelId === '') {
        $timeLabelId = null;
    }

    $summary = array_key_exists('summary', $payload)
        ? trim((string)$payload['summary'])
        : null;

    if ($summary === '') {
        $summary = null;
    }

    $notes = array_key_exists('notes', $payload)
        ? trim((string)$payload['notes'])
        : null;

    if ($notes === '') {
        $notes = null;
    }

    if ($timeLabelId !== null) {
        $labelStmt = $pdo->prepare(
            '
            SELECT label
            FROM calendar_time_label_classvals
            WHERE TRIM(id) = TRIM(:id)
            LIMIT 1
            '
        );

        $labelStmt->execute([
            ':id' => $timeLabelId,
        ]);

        $classvalLabel = $labelStmt->fetchColumn();

        if (!is_string($classvalLabel) || trim($classvalLabel) === '') {
            throw new RuntimeException(
                'Invalid calendar Book time label id'
            );
        }

        $summary = trim($classvalLabel);
    }

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

    if ($timeIndex < 1) {

        throw new RuntimeException(
            'calendar_book_time_create requires positive time_index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical parent week containment validation
    |--------------------------------------------------------------------------
    */

    $parentWeekStmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            projection_id
        FROM calendar_book_weeks
        WHERE id = :week_id
          AND projection_id = :projection_id
        LIMIT 1
    ");

    $parentWeekStmt->execute([
        ':week_id' => $weekId,
        ':projection_id' => $projectionId,
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
        LIMIT 1
    ");

    $parentDayStmt->execute([
        ':day_id' => $dayId,
        ':projection_id' => $projectionId,
        ':week_id' => $weekId,
    ]);

    $parentDay = $parentDayStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($parentDay)) {

        throw new RuntimeException(
            'Canonical parent Book day does not exist'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical chronology container materialization
    |--------------------------------------------------------------------------
    |
    | The Tier 0 materializer is intentionally ensure-like for a specific
    | canonical locality. Replays of the same payload should resolve the same
    | Book time row rather than failing solely because the row already exists.
    |
    */

    $time = materialize_calendar_book_time(
        $pdo,
        $projectionId,
        $dayId,
        $timeIndex
    );

    /*
    |--------------------------------------------------------------------------
    | Optional descriptive metadata
    |--------------------------------------------------------------------------
    */

    $updateFields = [];

    $updateParams = [
        ':id' => (int)$time['id'],
    ];

    if ($timeLabelId !== null) {

        $updateFields[] = 'time_label_id = :time_label_id';

        $updateParams[':time_label_id'] = $timeLabelId;
    }

    if ($summary !== null) {

        $updateFields[] = 'summary = :summary';

        $updateParams[':summary'] = $summary;
    }

    if ($notes !== null) {

        $updateFields[] = 'notes = :notes';

        $updateParams[':notes'] = $notes;
    }

    if ($updateFields !== []) {

        $updateFields[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare(
            '
            UPDATE calendar_book_times
            SET ' . implode(",\n                ", $updateFields) . '
            WHERE id = :id
            '
        );

        $stmt->execute($updateParams);

        $reload = $pdo->prepare("
            SELECT *
            FROM calendar_book_times
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
    | Derived projection rows are event-backed, not empty-container-backed.
    |--------------------------------------------------------------------------
    |
    | Creating a Book time container does not itself create or mutate any
    | calendar_event projection rows, so this workflow should not require a
    | projection rebuild. Avoiding rebuild here also keeps Tier 0 container
    | creation compatible with runtime DB users that do not have DELETE on the
    | derived projection surface.
    |
    */

    $operatorSqlDropin = fw_workflow_calendar_book_time_sql_dropin(
        (int)$time['id']
    );

    return [

        'success' => true,

        'context' => array_merge(
            $context,
            [

                'calendar_book_time' => $time,
                'book_time' => $time,
                'operator_sql_dropin' => $operatorSqlDropin,

                'calendar_book_time_create' => [

                    'projection_id' => $projectionId,

                    'week_id' => $weekId,

                    'day_id' => $dayId,

                    'time_index' => $timeIndex,

                    'calendar_book_time_id'
                        => (int)$time['id'],

                    'calendar_time_id'
                        => (int)$time['id'],

                    'entity_id'
                        => $time['entity_id'] ?? null,

                    'summary'
                        => $time['summary'] ?? null,

                    'sequence_index'
                        => isset($time['sequence_index'])
                            ? (int)$time['sequence_index']
                            : null,

                    'projection_build_id'
                        => null,
                ],

                'projection_build_id'
                    => null,
            ]
        ),
    ];
}
