<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__ . '/workflow_sql_dropin_builder.php';

require_once __DIR__
    . '/../calendar/admin/calendar_book_chronology_materializer.php';

require_once __DIR__
    . '/../calendar/calendar_projection_materializer.php';

function fw_execute_workflow_calendar_book_week_create(
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
    |   calendar_book_weeks
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

    $weekIndex = (int)($payload['week_index'] ?? 0);

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
            'calendar_book_week_create requires projection_id'
        );
    }

    if ($weekIndex < 1) {

        throw new RuntimeException(
            'calendar_book_week_create requires positive week_index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical chronology container materialization
    |--------------------------------------------------------------------------
    */

    $week = materialize_calendar_book_week(
        $pdo,
        $projectionId,
        $weekIndex
    );

    /*
    |--------------------------------------------------------------------------
    | Optional metadata enrichment
    |--------------------------------------------------------------------------
    |
    | chronology locality remains:
    |
    |   projection_id + week_index
    |
    | These fields are descriptive only.
    |
    */

    $updateFields = [];
    $updateParams = [
        ':id' => (int)$week['id'],
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
            UPDATE sxnzlfun_chrysalis.calendar_book_weeks
            SET ' . implode(",\n                ", $updateFields) . '
            WHERE id = :id
            '
        );

        $stmt->execute($updateParams);

        $reload = $pdo->prepare("
            SELECT *
            FROM sxnzlfun_chrysalis.calendar_book_weeks
            WHERE id = :id
            LIMIT 1
        ");

        $reload->execute([
            ':id' => (int)$week['id'],
        ]);

        $reloaded = $reload->fetch(PDO::FETCH_ASSOC);

        if (is_array($reloaded)) {
            $week = $reloaded;
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

    $operatorSqlDropin = fw_workflow_calendar_book_week_sql_dropin(
        (int)$week['id']
    );

    return [

        'success' => true,

        'context' => array_merge(
            $context,
            [

                'calendar_book_week' => $week,
                'operator_sql_dropin' => $operatorSqlDropin,

                'calendar_book_week_create' => [

                    'projection_id' => $projectionId,

                    'week_index' => $weekIndex,

                    'calendar_book_week_id'
                        => (int)$week['id'],

                    'entity_id'
                        => $week['entity_id'] ?? null,

                    'projection_build_id'
                        => $projectionBuildId,
                ],

                'projection_build_id' => $projectionBuildId,
            ]
        ),
    ];
}
