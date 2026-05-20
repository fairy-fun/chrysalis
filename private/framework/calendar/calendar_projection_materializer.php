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
    return [];
}
