<?php

require_once __DIR__ . '/calendar_date_resolver.php';

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

        // 4. Fetch source events
        $events = fetch_projection_source_events($pdo, $projectionId);

        // 5. Insert projection rows
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

            assert_calendar_projection_row_integrity($row, $projectionType);

            $insert->execute($row);
        }

        // 6. Validate complete build before marking valid
        assert_projection_build_integrity($pdo, $buildId, $projectionId, $projectionType);

        // 7. Mark valid
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

        'render_label' => $event['render_label'] ?? $event['summary'] ?? null,
        'notes' => $event['notes'] ?? null,
    ];

    if ($projectionType === 'time') {
        $row['projection_starts_at'] = resolve_calendar_datetime(
            $pdo,
            $event['real_date_start_id'] ?? null
        );

        $row['projection_ends_at'] = resolve_calendar_datetime(
            $pdo,
            $event['real_date_end_id'] ?? null
        );
    } elseif ($projectionType === 'structure') {
        $row['projection_address'] = $event['projection_address'] ?? $event['address'] ?? null;
    } elseif ($projectionType === 'book1') {
        $row['chronology_address'] = $event['chronology_address'] ?? null;
    } else {
        throw new RuntimeException("Unknown projection type: {$projectionType}");
    }

    return $row;
}

function fetch_calendar_projection_type(PDO $pdo, int $projectionId): string
{
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
        throw new RuntimeException("Projection not found: {$projectionId}");
    }

    $validTypes = ['time', 'structure', 'book1'];

    if (!in_array($type, $validTypes, true)) {
        throw new RuntimeException("Invalid projection type: {$type}");
    }

    return $type;
}

function fetch_projection_source_events(PDO $pdo, int $projectionId): array
{
    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.summary,
            e.notes,
            e.parent_event_id,
            e.sequence_index,
            e.chronology_address,
            e.real_date_start_id,
            e.real_date_end_id,
            e.projection_entity_id
        FROM calendar_events e
        LEFT JOIN calendar_projections p
          ON p.projection_code = e.projection_entity_id
        WHERE
            e.projection_id = :projection_id
            OR (
                e.projection_id IS NULL
                AND p.id = :projection_id
            )
    ");

    $stmt->execute([
        'projection_id' => $projectionId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        throw new RuntimeException("Projection build {$buildId} produced no rows.");
    }

    if ($projectionType === 'time') {
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
            throw new RuntimeException("Time projection build {$buildId} has rows missing projection_starts_at.");
        }
    }


    if ($projectionType === 'structure') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM calendar_event_projections
            WHERE build_id = :build_id
              AND calendar_projection_id = :projection_id
              AND projection_address IS NULL
        ");

        $stmt->execute([
            'build_id' => $buildId,
            'projection_id' => $projectionId,
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException("Structure projection build {$buildId} has rows missing projection_address.");
        }
    }

    if ($projectionType === 'book1') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM calendar_event_projections
            WHERE build_id = :build_id
              AND calendar_projection_id = :projection_id
              AND chronology_address IS NULL
        ");

        $stmt->execute([
            'build_id' => $buildId,
            'projection_id' => $projectionId,
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException("Book 1 projection build {$buildId} has rows missing chronology_address.");
        }
    }
}



function assert_calendar_projection_row_integrity(array $row, string $projectionType): void
{
    if (empty($row['build_id'])) {
        throw new RuntimeException('Projection row missing build_id.');
    }

    if (empty($row['calendar_event_id'])) {
        throw new RuntimeException('Projection row missing calendar_event_id.');
    }

    if (empty($row['calendar_projection_id'])) {
        throw new RuntimeException('Projection row missing calendar_projection_id.');
    }

    if ($projectionType === 'time') {
        if ($row['projection_starts_at'] === null) {
            throw new RuntimeException('Time projection row missing projection_starts_at.');
        }

        if ($row['projection_address'] !== null) {
            throw new RuntimeException('Time projection row must not use projection_address.');
        }

        if ($row['chronology_address'] !== null) {
            throw new RuntimeException('Time projection row must not use chronology_address.');
        }
    }

    if ($projectionType === 'structure') {
        if ($row['projection_address'] === null) {
            throw new RuntimeException('Structure projection row missing projection_address.');
        }

        if ($row['projection_starts_at'] !== null || $row['projection_ends_at'] !== null) {
            throw new RuntimeException('Structure projection row must not use temporal fields.');
        }

        if ($row['chronology_address'] !== null) {
            throw new RuntimeException('Structure projection row must not use chronology_address.');
        }
    }

    if ($projectionType === 'book1') {
        if ($row['chronology_address'] === null) {
            throw new RuntimeException('Book 1 projection row missing chronology_address.');
        }

        if ($row['projection_address'] !== null) {
            throw new RuntimeException('Book 1 projection row must not use projection_address.');
        }

        if ($row['projection_starts_at'] !== null || $row['projection_ends_at'] !== null) {
            throw new RuntimeException('Book 1 projection row must not use temporal fields.');
        }
    }
}

