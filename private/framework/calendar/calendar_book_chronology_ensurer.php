<?php

declare(strict_types=1);

/**
 * Canonical Book chronology resolution.
 *
 * Book chronology containers live in:
 *
 *   calendar_book_weeks
 *       -> calendar_book_days
 *           -> calendar_book_times
 *
 * This file resolves Book chronology tuple locality into canonical
 * calendar_book_times.id values.
 *
 * It must not create or infer recursive calendar_events ancestry.
 */

function resolve_calendar_book_time_id(
    PDO $pdo,
    int $projectionId,
    int $weekIndex,
    int $dayIndex,
    int $timeIndex
): int {

    if ($projectionId < 1) {
        throw new InvalidArgumentException(
            'projection_id must be positive'
        );
    }

    if ($weekIndex < 1) {
        throw new InvalidArgumentException(
            'week_index must be positive'
        );
    }

    if ($dayIndex < 1) {
        throw new InvalidArgumentException(
            'day_index must be positive'
        );
    }

    if ($timeIndex < 1) {
        throw new InvalidArgumentException(
            'time_index must be positive'
        );
    }

    assert_calendar_book_projection_exists($pdo, $projectionId);

    $stmt = $pdo->prepare("
        SELECT cbt.id
        FROM calendar_book_times cbt

        INNER JOIN calendar_book_days cbd
            ON cbd.id = cbt.day_id

        INNER JOIN calendar_book_weeks cbw
            ON cbw.id = cbd.week_id

        WHERE cbw.projection_id = :projection_id
          AND cbd.projection_id = :projection_id
          AND cbt.projection_id = :projection_id

          AND cbw.week_index = :week_index
          AND cbd.day_index = :day_index
          AND cbt.time_index = :time_index

        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
        ':day_index' => $dayIndex,
        ':time_index' => $timeIndex,
    ]);

    $bookTimeId = $stmt->fetchColumn();

    if ($bookTimeId === false || (int)$bookTimeId < 1) {
        throw new RuntimeException(
            'calendar_book_time not found for Book chronology tuple'
        );
    }

    return (int)$bookTimeId;
}

function resolve_calendar_book_time_row(
    PDO $pdo,
    int $projectionId,
    int $weekIndex,
    int $dayIndex,
    int $timeIndex
): array {

    $bookTimeId = resolve_calendar_book_time_id(
        $pdo,
        $projectionId,
        $weekIndex,
        $dayIndex,
        $timeIndex
    );

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_book_times
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $bookTimeId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'calendar_book_time row not found after id resolution'
        );
    }

    return $row;
}

function assert_calendar_book_projection_exists(
    PDO $pdo,
    int $projectionId
): void {

    if ($projectionId < 1) {
        throw new InvalidArgumentException(
            'projection_id must be positive'
        );
    }

    $stmt = $pdo->prepare("
        SELECT projection_type_id
        FROM calendar_projections
        WHERE id = :projection_id
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
    ]);

    $projectionTypeId = $stmt->fetchColumn();

    if (
        !is_string($projectionTypeId)
        || trim($projectionTypeId) === ''
    ) {
        throw new RuntimeException(
            'calendar projection not found'
        );
    }

    if (trim($projectionTypeId) !== 'projection_type_book') {
        throw new RuntimeException(
            'projection is not a Book projection'
        );
    }
}