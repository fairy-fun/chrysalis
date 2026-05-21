<?php

declare(strict_types=1);

/**
 * Tier 0 Book chronology materializer.
 *
 * This file is for chronology-container authoring/bootstrap only.
 *
 * It must not be required by Tier 1 calendar event creation paths.
 * Tier 1 event creation must resolve existing chronology containers
 * through calendar_book_chronology_ensurer.php instead.
 */

require_once dirname(__DIR__) . '/calendar_book_chronology_ensurer.php';

function materialize_calendar_book_week(
    PDO $pdo,
    int $projectionId,
    int $weekIndex
): array {

    assert_calendar_book_projection_exists($pdo, $projectionId);

    if ($weekIndex < 1) {
        throw new InvalidArgumentException('week_index must be positive');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_book_weeks
        WHERE projection_id = :projection_id
          AND week_index = :week_index
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($row)) {
        return $row;
    }

    $insert = $pdo->prepare("
        INSERT INTO calendar_book_weeks (
            projection_id,
            week_index,
            created_at,
            updated_at
        )
        VALUES (
            :projection_id,
            :week_index,
            NOW(),
            NOW()
        )
    ");

    $insert->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
    ]);

    return reload_calendar_book_week(
        $pdo,
        (int)$pdo->lastInsertId()
    );
}

function materialize_calendar_book_day(
    PDO $pdo,
    int $projectionId,
    int $weekId,
    int $dayIndex,
    string $dayOfWeekId
): array {

    assert_calendar_book_projection_exists($pdo, $projectionId);

    if ($weekId < 1) {
        throw new InvalidArgumentException('week_id must be positive');
    }

    if ($dayIndex < 1) {
        throw new InvalidArgumentException('day_index must be positive');
    }

    $dayOfWeekId = trim($dayOfWeekId);

    if ($dayOfWeekId === '') {
        throw new InvalidArgumentException('day_of_week_id is required');
    }

    $parentStmt = $pdo->prepare("
        SELECT id
        FROM calendar_book_weeks
        WHERE id = :week_id
          AND projection_id = :projection_id
        LIMIT 1
    ");

    $parentStmt->execute([
        ':week_id' => $weekId,
        ':projection_id' => $projectionId,
    ]);

    $parentWeekId = $parentStmt->fetchColumn();

    if ($parentWeekId === false || (int)$parentWeekId < 1) {
        throw new RuntimeException('calendar_book_week parent not found');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_book_days
        WHERE projection_id = :projection_id
          AND week_id = :week_id
          AND day_index = :day_index
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':week_id' => $weekId,
        ':day_index' => $dayIndex,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($row)) {
        return $row;
    }

    $insert = $pdo->prepare("
        INSERT INTO calendar_book_days (
            projection_id,
            week_id,
            day_index,
            day_of_week_id,
            created_at,
            updated_at
        )
        VALUES (
            :projection_id,
            :week_id,
            :day_index,
            :day_of_week_id,
            NOW(),
            NOW()
        )
    ");

    $insert->execute([
        ':projection_id' => $projectionId,
        ':week_id' => $weekId,
        ':day_index' => $dayIndex,
        ':day_of_week_id' => $dayOfWeekId,
    ]);

    return reload_calendar_book_day(
        $pdo,
        (int)$pdo->lastInsertId()
    );
}

function reload_calendar_book_week(
    PDO $pdo,
    int $weekId
): array {

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_book_weeks
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $weekId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('calendar_book_week reload failed');
    }

    return $row;
}

function reload_calendar_book_day(
    PDO $pdo,
    int $dayId
): array {

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_book_days
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $dayId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('calendar_book_day reload failed');
    }

    return $row;
}
