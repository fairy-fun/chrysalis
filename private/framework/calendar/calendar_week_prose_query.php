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