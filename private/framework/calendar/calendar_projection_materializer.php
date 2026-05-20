<?php

require_once __DIR__ . '/calendar_projection_resolver.php';
require_once __DIR__ . '/calendar_date_resolver.php';
require_once dirname(__DIR__) . '/procedures/materialize_calendar_chronology.php';

function rebuild_calendar_projection(PDO $pdo, int $projectionId): int
{
    $buildId = null;

    try {
        $pdo->beginTransaction();

        // 1. Create build
        $stmt = $pdo->prepare("
            INSERT INTO calendar_projection_builds (
                projection_id,
                status
            )
            VALUES (
                :projection_id,
                'pending'
            )
        ");
        $stmt->execute([
            'projection_id' => $projectionId,
        ]);

        $buildId = (int)$pdo->lastInsertId();

        // 2. Mark build as building
        $pdo->prepare("
            UPDATE calendar_projection_builds
            SET status = 'building'
            WHERE id = :id
        ")->execute([
            'id' => $buildId,
        ]);

        // 3. Resolve projection type
        $projectionType = fetch_calendar_projection_type($pdo, $projectionId);

        // 4. Materialize chronology before projection rows are read.
        materialize_calendar_chronology(
            $pdo,
            $projectionId
        );

        // 5. Fetch source events
        $events = fetch_projection_source_events(
            $pdo,
            $projectionId,
            $projectionType
        );

        // 6. Insert projection rows
        $insert = $pdo->prepare("
            INSERT INTO calendar_event_projections (
                build_id,
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
                :build_id,
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
        ");

        foreach ($events as $sequence => $event) {
            $row = build_calendar_projection_row(
                $pdo,
                $event,
                $projectionId,
                $buildId,
                $projectionType,
                $sequence
            );

            assert_calendar_projection_row_integrity(
                $row,
                $projectionType
            );

            $insert->execute($row);
        }

        // 7. Validate complete build before marking valid
        assert_projection_build_integrity(
            $pdo,
            $buildId,
            $projectionId,
            $projectionType
        );

        // 8. Mark valid
        $pdo->prepare("
            UPDATE calendar_projection_builds
            SET status = 'valid',
                validated_at = NOW()
            WHERE id = :id
        ")->execute([
            'id' => $buildId,
        ]);

        $pdo->commit();

        return $buildId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($buildId !== null) {
            $pdo->prepare("
                UPDATE calendar_projection_builds
                SET status = 'failed'
                WHERE id = :id
            ")->execute([
                'id' => $buildId,
            ]);
        }

        throw $e;
    }
}

function build_calendar_projection_row(
    PDO $pdo,
    array $event,
    int $projectionId,
    int $buildId,
    string $projectionType,
    int $sequence
): array {
    $row = [
        'build_id' => $buildId,
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

        /*
         * Render identity only.
         *
         * Book chronology locality is carried by book_time_id + event_index.
         * chronology_address is a derived display/export address and must not
         * be read back from calendar_events as canonical source data.
         */
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
    $orderBy = calendar_projection_source_order_by(
        $projectionType
    );

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

    if ($projectionType === 'projection_type_journal') {
        throw new RuntimeException(
            'Journal projection materialization is not implemented.'
        );
    }

    throw new RuntimeException(
        "Unknown projection type: {$projectionType}"
    );
}

function assert_projection_build_integrity(
    PDO $pdo,
    int $buildId,
    int $projectionId,
    string $projectionType
): void {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM calendar_event_projections
        WHERE build_id = :build_id
          AND calendar_projection_id = :projection_id
    ");

    $stmt->execute([
        'build_id' => $buildId,
        'projection_id' => $projectionId,
    ]);

    $rowCount = (int)$stmt->fetchColumn();

    if ($rowCount === 0) {
        throw new RuntimeException(
            "Projection build {$buildId} produced no rows."
        );
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM calendar_event_projections cep
        LEFT JOIN calendar_events ce
            ON ce.id = cep.calendar_event_id
        WHERE cep.build_id = :build_id
          AND cep.calendar_projection_id = :projection_id
          AND ce.id IS NULL
    ");

    $stmt->execute([
        'build_id' => $buildId,
        'projection_id' => $projectionId,
    ]);

    $orphanCount = (int)$stmt->fetchColumn();

    if ($orphanCount > 0) {
        throw new RuntimeException(
            "Projection build {$buildId} contains {$orphanCount} orphan calendar event reference(s)."
        );
    }

    if ($projectionType === 'projection_type_timeline_view') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM calendar_event_projections
            WHERE build_id = :build_id
              AND calendar_projection_id = :projection_id
              AND projection_starts_at IS NULL
        ");

        $stmt->execute([
            'build_id' => $buildId,
            'projection_id' => $projectionId,
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                "Timeline projection build {$buildId} has rows missing projection_starts_at."
            );
        }
    }

    if ($projectionType === 'projection_type_book') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM calendar_event_projections cep
            INNER JOIN calendar_events ce
                ON ce.id = cep.calendar_event_id
            WHERE cep.build_id = :build_id
              AND cep.calendar_projection_id = :projection_id
              AND (
                  ce.book_time_id IS NULL
                  OR ce.event_index IS NULL
              )
        ");

        $stmt->execute([
            'build_id' => $buildId,
            'projection_id' => $projectionId,
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                "Book projection build {$buildId} has rows missing canonical Book locality."
            );
        }
    }

    if ($projectionType === 'projection_type_journal') {
        throw new RuntimeException(
            'Journal projection materialization is not implemented.'
        );
    }
}

function assert_calendar_projection_row_integrity(
    array $row,
    string $projectionType
): void {
    if (empty($row['build_id'])) {
        throw new RuntimeException(
            'Projection row missing build_id.'
        );
    }

    if (empty($row['calendar_event_id'])) {
        throw new RuntimeException(
            'Projection row missing calendar_event_id.'
        );
    }

    if (empty($row['calendar_projection_id'])) {
        throw new RuntimeException(
            'Projection row missing calendar_projection_id.'
        );
    }

    if ($projectionType === 'projection_type_timeline_view') {

        if ($row['projection_starts_at'] === null) {
            throw new RuntimeException(
                'Timeline projection row missing projection_starts_at.'
            );
        }

        if ($row['projection_address'] !== null) {
            throw new RuntimeException(
                'Timeline projection row must not use projection_address.'
            );
        }

        if ($row['chronology_address'] !== null) {
            throw new RuntimeException(
                'Timeline projection row must not use chronology_address.'
            );
        }
    }

    if ($projectionType === 'projection_type_book') {

        if ($row['projection_address'] !== null) {
            throw new RuntimeException(
                'Book projection row must not use projection_address.'
            );
        }

        if (
            $row['projection_starts_at'] !== null ||
            $row['projection_ends_at'] !== null
        ) {
            throw new RuntimeException(
                'Book projection row must not use temporal fields.'
            );
        }
    }

    if ($projectionType === 'projection_type_journal') {
        throw new RuntimeException(
            'Journal projection materialization is not implemented.'
        );
    }
}
