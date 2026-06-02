<?php

/**
 * PostgreSQL-compatible version of calendar_projection_materializer.php
 *
 * Changes from MySQL original:
 * - INSERT ... ON DUPLICATE KEY UPDATE  →  INSERT ... ON CONFLICT (...) DO UPDATE SET
 * - is_calendar_projection_delete_permission_error: MySQL error 1142
 *     →  PostgreSQL SQLSTATE 42501 (insufficient_privilege)
 *
 * Constraint relied upon:
 *   uq_calendar_event_projection_pair (calendar_event_id, calendar_projection_id)
 */

require_once __DIR__ . '/../../framework/calendar/calendar_projection_resolver.php';
require_once __DIR__ . '/../../framework/calendar/calendar_date_resolver.php';
require_once __DIR__ . '/../../framework/procedures/materialize_calendar_chronology.php';

function rebuild_calendar_projection_pg(PDO $pdo, int $projectionId): int
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
        // runtime permissions allow it.
        $performedFullClear = clear_calendar_projection_rows_for_rebuild_pg(
            $pdo,
            $projectionId
        );

        // 4. Fetch source events
        $events = fetch_projection_source_events(
            $pdo,
            $projectionId,
            $projectionType
        );

        // 5. Insert or refresh projection rows (PostgreSQL upsert)
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
            ON CONFLICT (calendar_event_id, calendar_projection_id)
            DO UPDATE SET
                projection_address       = EXCLUDED.projection_address,
                chronology_address       = EXCLUDED.chronology_address,
                projection_starts_at     = EXCLUDED.projection_starts_at,
                projection_ends_at       = EXCLUDED.projection_ends_at,
                parent_projection_row_id = EXCLUDED.parent_projection_row_id,
                projection_sequence      = EXCLUDED.projection_sequence,
                render_label             = EXCLUDED.render_label,
                notes                    = EXCLUDED.notes,
                updated_at               = NOW()
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

function clear_calendar_projection_rows_for_rebuild_pg(
    PDO $pdo,
    int $projectionId
): bool {

    try {
        $delete = $pdo->prepare("
            DELETE FROM calendar_event_projections
            WHERE calendar_projection_id = :calendar_projection_id
        ");

        $delete->execute([
            'calendar_projection_id' => $projectionId,
        ]);

        return true;
    } catch (PDOException $e) {
        if (!is_calendar_projection_delete_permission_error_pg($e)) {
            throw $e;
        }

        return false;
    }
}

function is_calendar_projection_delete_permission_error_pg(
    PDOException $e
): bool {

    // PostgreSQL SQLSTATE 42501 = insufficient_privilege
    // PDO exposes SQLSTATE via $e->getCode() or $e->errorInfo[0]
    $sqlstate = $e->errorInfo[0] ?? $e->getCode();

    return $sqlstate === '42501';
}
