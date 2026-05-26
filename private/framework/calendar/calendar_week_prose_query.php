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