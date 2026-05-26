<?php

declare(strict_types=1);

function query_calendar_week(
    PDO $pdo,
    int $projectionId,
    int $weekIndex
): ?array {

    $stmt = $pdo->prepare("
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

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
    ]);

    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($week)
        ? $week
        : null;
}

function query_calendar_week_days(
    PDO $pdo,
    int $projectionId,
    int $weekId
): array {

    $stmt = $pdo->prepare("
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

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':week_id' => $weekId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function query_calendar_day_times(
    PDO $pdo,
    int $projectionId,
    int $dayId
): array {

    $stmt = $pdo->prepare("
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

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':day_id' => $dayId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function query_calendar_time_events(
    PDO $pdo,
    int $projectionId,
    int $bookTimeId,
    string $proseMode
): array {

    $exportPredicate = $proseMode === 'export'
        ? ' AND is_export_target = 1'
        : '';

    $exportPredicateP1 = $proseMode === 'export'
        ? ' AND p1.is_export_target = 1'
        : '';

    $exportPredicatePOrder = $proseMode === 'export'
        ? ' AND p_order.is_export_target = 1'
        : '';

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
            e.chronology_address,

            cerl.reference_label,

            pp.id AS prose_projection_id,
            pp.published_prose_draft_id,
            pp.projection_order AS prose_projection_order,
            pp.is_export_target,

            pd.prose_body

        FROM calendar_events e

        LEFT JOIN calendar_event_reference_labels cerl
            ON cerl.calendar_event_id = e.id

        LEFT JOIN (
            SELECT
                p1.target_entity_id,
                p1.id,
                p1.published_prose_draft_id,
                p1.projection_order,
                p1.is_export_target
            FROM prose_projections p1
            INNER JOIN (
                SELECT
                    p_order.target_entity_id,
                    MIN(p_order.id) AS resolved_projection_id
                FROM prose_projections p_order
                INNER JOIN (
                    SELECT
                        target_entity_id,
                        MIN(projection_order) AS resolved_projection_order
                    FROM prose_projections
                    WHERE published_prose_draft_id IS NOT NULL
                      AND projection_order IS NOT NULL
                      AND role_id = 'prose_projection_role_primary'
                      AND projection_type_id = 'projection_type_timeline_view'
                      {$exportPredicate}
                    GROUP BY target_entity_id
                ) resolved_order
                    ON resolved_order.target_entity_id = p_order.target_entity_id
                   AND resolved_order.resolved_projection_order = p_order.projection_order
                WHERE p_order.published_prose_draft_id IS NOT NULL
                  AND p_order.projection_order IS NOT NULL
                  AND p_order.role_id = 'prose_projection_role_primary'
                  AND p_order.projection_type_id = 'projection_type_timeline_view'
                  {$exportPredicatePOrder}
                GROUP BY p_order.target_entity_id
            ) resolved
                ON resolved.resolved_projection_id = p1.id
            WHERE p1.published_prose_draft_id IS NOT NULL
              AND p1.projection_order IS NOT NULL
              AND p1.role_id = 'prose_projection_role_primary'
              AND p1.projection_type_id = 'projection_type_timeline_view'
              {$exportPredicateP1}
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
            e.entity_id ASC
    ");

    $eventsStmt->execute([
        ':projection_id' => $projectionId,
        ':book_time_id' => $bookTimeId,
    ]);

    return $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
}