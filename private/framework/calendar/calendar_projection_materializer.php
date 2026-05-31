<?php

require_once __DIR__ . '/calendar_projection_resolver.php';
require_once __DIR__ . '/calendar_date_resolver.php';
require_once dirname(__DIR__) . '/procedures/materialize_calendar_chronology.php';

function rebuild_calendar_projection(PDO $pdo, int $projectionId): int
{
    $pdo->beginTransaction();

    try {
        // 1. Resolve projection type
        $projectionType = fetch_calendar_projection_type($pdo, $projectionId);

        // 2. Materialize chronology before projection rows are read
        materialize_calendar_chronology(
            $pdo,
            $projectionId
        );

        // 3. Clear the derived projection surface for a full rebuild when
        // runtime permissions allow it. Some workflow DB users are allowed to
        // insert/update derived projection rows but not delete them.
        $performedFullClear = clear_calendar_projection_rows_for_rebuild(
            $pdo,
            $projectionId
        );

        // 4. Fetch source events
        $events = fetch_projection_source_events(
            $pdo,
            $projectionId,
            $projectionType
        );

        // 5. Insert or refresh projection rows
        $insert = $pdo->prepare("
            INSERT INTO calendar_event_projections (
                calendar_event_id,
                calendar_projection_id,

                projection_address,
                chronology_address,

                projection_starts_at,
                projection_ends_at,

                parent_projection_row_id,
                projection_sequence,

                render_label,
                notes,

                created_at,
                updated_at
            )
            VALUES (
                :calendar_event_id,
                :calendar_projection_id,

                :projection_address,
                :chronology_address,

                :projection_starts_at,
                :projection_ends_at,

                :parent_projection_row_id,
                :projection_sequence,

                :render_label,
                :notes,

                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                projection_address = VALUES(projection_address),
                chronology_address = VALUES(chronology_address),
                projection_starts_at = VALUES(projection_starts_at),
                projection_ends_at = VALUES(projection_ends_at),
                parent_projection_row_id = VALUES(parent_projection_row_id),
                projection_sequence = VALUES(projection_sequence),
                render_label = VALUES(render_label),
                notes = VALUES(notes),
                updated_at = NOW()
        ");

        foreach ($events as $sequence => $event) {
            $row = build_calendar_projection_row(
                $pdo,
                $event,
                $projectionId,
                $projectionType,
                $sequence
            );

            $insert->execute($row);
        }

        $pdo->commit();

        return $projectionId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function clear_calendar_projection_rows_for_rebuild(
    PDO $pdo,
    int $projectionId
): bool {

    $delete = $pdo->prepare("
        DELETE FROM calendar_event_projections
        WHERE calendar_projection_id = :calendar_projection_id
    ");

    try {
        $delete->execute([
            'calendar_projection_id' => $projectionId,
        ]);

        return true;
    } catch (PDOException $e) {
        if (!is_calendar_projection_delete_permission_error($e)) {
            throw $e;
        }

        return false;
    }
}

function is_calendar_projection_delete_permission_error(
    PDOException $e
): bool {

    $info = $e->errorInfo ?? null;

    if (!is_array($info)) {
        return false;
    }

    $driverCode = isset($info[1]) ? (int)$info[1] : 0;
    $driverMessage = isset($info[2]) ? (string)$info[2] : '';

    return $driverCode === 1142
        && str_contains($driverMessage, 'DELETE command denied')
        && str_contains($driverMessage, 'calendar_event_projections');
}

function build_calendar_projection_row(
    PDO $pdo,
    array $event,
    int $projectionId,
    string $projectionType,
    int $sequence
): array {
    $row = [
        'calendar_event_id' => $event['id'],
        'calendar_projection_id' => $projectionId,

        'projection_address' => null,
        'chronology_address' => null,

        'projection_starts_at' => null,
        'projection_ends_at' => null,

        'parent_projection_row_id' => null,
        'projection_sequence' => $sequence,

        'render_label' => $event['render_label']
            ?? $event['summary']
            ?? null,

        'notes' => $event['notes'] ?? null,
    ];

    if ($projectionType === 'projection_type_timeline_view') {
        $row['projection_starts_at'] = resolve_calendar_datetime(
            $pdo,
            $event['real_date_start_id'] ?? null
        );

        $row['projection_ends_at'] = resolve_calendar_datetime(
            $pdo,
            $event['real_date_end_id'] ?? null
        );

    } elseif ($projectionType === 'projection_type_book') {

        $row['chronology_address'] = build_materialized_book_chronology_address($event);

    } elseif ($projectionType === 'projection_type_journal') {

        throw new RuntimeException(
            'Journal projection materialization is not implemented.'
        );

    } else {
        throw new RuntimeException(
            "Unknown projection type: {$projectionType}"
        );
    }

    return $row;
}

function build_materialized_book_chronology_address(
    array $event
): string {

    $weekIndex = (int)($event['book_week_index'] ?? 0);
    $dayIndex = (int)($event['book_day_index'] ?? 0);
    $timeIndex = (int)($event['book_time_index'] ?? 0);
    $eventIndex = (int)($event['event_index'] ?? 0);

    if (
        $weekIndex < 1 ||
        $dayIndex < 1 ||
        $timeIndex < 1 ||
        $eventIndex < 1
    ) {
        throw new RuntimeException(
            'Cannot derive chronology_address without canonical Book chronology containers'
        );
    }

    return implode('.', [
        $weekIndex,
        $dayIndex,
        $timeIndex,
        $eventIndex,
    ]);
}

function fetch_calendar_projection_type(
    PDO $pdo,
    int $projectionId
): string {
    $stmt = $pdo->prepare("
        SELECT projection_type_id
        FROM calendar_projections
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $projectionId,
    ]);

    $type = $stmt->fetchColumn();

    if ($type === false) {
        throw new RuntimeException(
            "Projection not found: {$projectionId}"
        );
    }

    $validTypes = [
        'projection_type_book',
        'projection_type_journal',
        'projection_type_timeline_view',
    ];

    if (!in_array($type, $validTypes, true)) {
        throw new RuntimeException(
            "Invalid projection type: {$type}"
        );
    }

    return $type;
}

function fetch_projection_source_events(
    PDO $pdo,
    int $projectionId,
    string $projectionType
): array {

    $orderBy = calendar_projection_source_order_by($projectionType);

    $whereFragments = [];

    if ($projectionType === 'projection_type_book') {
        $whereFragments[] = '
            e.book_time_id IS NOT NULL
            AND e.event_index IS NOT NULL
            AND cbt.id IS NOT NULL
            AND cbd.id IS NOT NULL
            AND cbw.id IS NOT NULL
        ';
    }

    $additionalWhere = '';

    if ($whereFragments !== []) {
        $additionalWhere = ' AND ' . implode(' AND ', $whereFragments);
    }

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            e.id,
            e.summary,
            e.notes,
            e.parent_event_id,
            e.sequence_index,
            e.chronology_address,
            e.book_time_id,
            e.event_index,
            e.real_date_start_id,
            e.real_date_end_id,
            e.projection_id,
            cbw.week_index AS book_week_index,
            cbd.day_index AS book_day_index,
            cbt.time_index AS book_time_index
        FROM calendar_events e
        INNER JOIN calendar_event_projection_membership m
            ON m.calendar_event_id = e.id
        LEFT JOIN calendar_book_times cbt
            ON cbt.id = e.book_time_id
        LEFT JOIN calendar_book_days cbd
            ON cbd.id = cbt.day_id
        LEFT JOIN calendar_book_weeks cbw
            ON cbw.id = cbd.week_id
        WHERE m.projection_id = :projection_id
        {$additionalWhere}
        {$orderBy}
    ");

    $stmt->execute([
        'projection_id' => $projectionId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calendar_projection_source_order_by(
    string $projectionType
): string {

    if ($projectionType === 'projection_type_book') {
        return "
            ORDER BY
                e.book_time_id ASC,
                e.event_index ASC,
                e.id ASC
        ";
    }

    if ($projectionType === 'projection_type_timeline_view') {
        return "
            ORDER BY
                e.real_date_start_id ASC,
                e.real_date_end_id ASC,
                e.id ASC
        ";
    }

    return "";
}
