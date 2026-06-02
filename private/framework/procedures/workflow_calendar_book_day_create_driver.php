<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__ . '/workflow_sql_dropin_builder.php';

require_once __DIR__
    . '/../calendar/admin/calendar_book_chronology_materializer.php';

require_once __DIR__
    . '/../calendar/calendar_projection_materializer.php';

function fw_execute_workflow_calendar_book_day_create(
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
    |   calendar_book_days
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

    $weekIndex = (int)($payload['week_index'] ?? 0);

    $dayIndex = (int)($payload['day_index'] ?? 0);

    $dayOfWeekId = trim((string)(
        $payload['day_of_week_id'] ?? ''
    ));

    $summary = array_key_exists('summary', $payload)
        ? trim((string)$payload['summary'])
        : null;

    $notes = array_key_exists('notes', $payload)
        ? trim((string)$payload['notes'])
        : null;

    $realDateStartId = array_key_exists('real_date_start_id', $payload)
        ? trim((string)$payload['real_date_start_id'])
        : null;

    $realDateEndId = array_key_exists('real_date_end_id', $payload)
        ? trim((string)$payload['real_date_end_id'])
        : null;

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($projectionId < 1) {

        throw new RuntimeException(
            'calendar_book_day_create requires projection_id'
        );
    }

    if ($weekId < 1) {

        throw new RuntimeException(
            'calendar_book_day_create requires week_id'
        );
    }

    if ($weekIndex < 1) {

        throw new RuntimeException(
            'calendar_book_day_create requires positive week_index'
        );
    }

    if ($dayIndex < 1) {

        throw new RuntimeException(
            'calendar_book_day_create requires positive day_index'
        );
    }

    if ($dayOfWeekId === '') {

        throw new RuntimeException(
            'calendar_book_day_create requires day_of_week_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical parent containment validation
    |--------------------------------------------------------------------------
    |
    | Day creation requires an existing canonical Book week container.
    |
    | Tier 0 may materialize days.
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
    | day_of_week authority validation
    |--------------------------------------------------------------------------
    |
    | Canonical Book day containers must reference a valid
    | day_of_week_classvals authority row.
    |
    */

    $dayOfWeekStmt = $pdo->prepare("
        SELECT
            id,
            code,
            label
        FROM day_of_week_classvals
        WHERE id = :id
        LIMIT 1
    ");

    $dayOfWeekStmt->execute([
        ':id' => $dayOfWeekId,
    ]);

    $dayOfWeek = $dayOfWeekStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($dayOfWeek)) {

        throw new RuntimeException(
            'Invalid day_of_week_id supplied'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical chronology container materialization
    |--------------------------------------------------------------------------
    */

    $day = materialize_calendar_book_day(
        $pdo,
        $projectionId,
        $weekId,
        $dayIndex,
        $dayOfWeekId
    );

    /*
    |--------------------------------------------------------------------------
    | Optional metadata enrichment
    |--------------------------------------------------------------------------
    |
    | chronology locality remains:
    |
    |   projection_id + week_id + day_index
    |
    | These fields are descriptive only.
    |
    */

    $updateFields = [];

    $updateParams = [
        ':id' => (int)$day['id'],
    ];

    if ($summary !== null && $summary !== '') {

        $updateFields[] = 'summary = :summary';

        $updateParams[':summary'] = $summary;
    }

    if ($notes !== null && $notes !== '') {

        $updateFields[] = 'notes = :notes';

        $updateParams[':notes'] = $notes;
    }

    if ($realDateStartId !== null && $realDateStartId !== '') {

        $updateFields[] = 'real_date_start_id = :real_date_start_id';

        $updateParams[':real_date_start_id'] = $realDateStartId;
    }

    if ($realDateEndId !== null && $realDateEndId !== '') {

        $updateFields[] = 'real_date_end_id = :real_date_end_id';

        $updateParams[':real_date_end_id'] = $realDateEndId;
    }

    if ($updateFields !== []) {

        $updateFields[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare(
            '
            UPDATE sxnzlfun_chrysalis.calendar_book_days
            SET ' . implode(",\n                ", $updateFields) . '
            WHERE id = :id
            '
        );

        $stmt->execute($updateParams);

        $reload = $pdo->prepare("
            SELECT *
            FROM sxnzlfun_chrysalis.calendar_book_days
            WHERE id = :id
            LIMIT 1
        ");

        $reload->execute([
            ':id' => (int)$day['id'],
        ]);

        $reloaded = $reload->fetch(PDO::FETCH_ASSOC);

        if (is_array($reloaded)) {
            $day = $reloaded;
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

    $operatorSqlDropin = fw_workflow_calendar_book_day_sql_dropin(
        (int)$day['id']
    );

    return [

        'success' => true,

        'context' => array_merge(
            $context,
            [

                'calendar_book_day' => $day,
                'operator_sql_dropin' => $operatorSqlDropin,

                'calendar_book_day_create' => [

                    'projection_id' => $projectionId,

                    'week_id' => $weekId,

                    'week_index' => $weekIndex,

                    'day_index' => $dayIndex,

                    'calendar_book_day_id'
                        => (int)$day['id'],

                    'entity_id'
                        => $day['entity_id'] ?? null,

                    'projection_build_id'
                        => $projectionBuildId,
                ],

                'projection_build_id' => $projectionBuildId,
            ]
        ),
    ];
}
