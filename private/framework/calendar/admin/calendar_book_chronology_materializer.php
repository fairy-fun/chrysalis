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

    $entityId = calendar_book_week_entity_id(
        $projectionId,
        $weekIndex
    );

    ensure_calendar_structure_entity_row(
        $pdo,
        $entityId,
        'entity_type_calendar_week'
    );

    $insert = $pdo->prepare("
        INSERT INTO calendar_book_weeks (
            projection_id,
            week_index,
            entity_id,
            created_at,
            updated_at
        )
        VALUES (
            :projection_id,
            :week_index,
            :entity_id,
            NOW(),
            NOW()
        )
    ");

    $insert->execute([
        ':projection_id' => $projectionId,
        ':week_index' => $weekIndex,
        ':entity_id' => $entityId,
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
        SELECT id, week_index
        FROM calendar_book_weeks
        WHERE id = :week_id
          AND projection_id = :projection_id
        LIMIT 1
    ");

    $parentStmt->execute([
        ':week_id' => $weekId,
        ':projection_id' => $projectionId,
    ]);

    $parentWeek = $parentStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($parentWeek)) {
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

    $entityId = calendar_book_day_entity_id(
        $projectionId,
        (int)$parentWeek['week_index'],
        $dayIndex
    );

    ensure_calendar_structure_entity_row(
        $pdo,
        $entityId,
        'entity_type_calendar_day'
    );

    $insert = $pdo->prepare("
        INSERT INTO calendar_book_days (
            projection_id,
            week_id,
            day_index,
            day_of_week_id,
            entity_id,
            created_at,
            updated_at
        )
        VALUES (
            :projection_id,
            :week_id,
            :day_index,
            :day_of_week_id,
            :entity_id,
            NOW(),
            NOW()
        )
    ");

    $insert->execute([
        ':projection_id' => $projectionId,
        ':week_id' => $weekId,
        ':day_index' => $dayIndex,
        ':day_of_week_id' => $dayOfWeekId,
        ':entity_id' => $entityId,
    ]);

    return reload_calendar_book_day(
        $pdo,
        (int)$pdo->lastInsertId()
    );
}

function materialize_calendar_book_time(
    PDO $pdo,
    int $projectionId,
    int $dayId,
    int $timeIndex
): array {

    assert_calendar_book_projection_exists($pdo, $projectionId);

    if ($dayId < 1) {
        throw new InvalidArgumentException('day_id must be positive');
    }

    if ($timeIndex < 1) {
        throw new InvalidArgumentException('time_index must be positive');
    }

    $parentStmt = $pdo->prepare("
        SELECT
            cbd.id,
            cbd.day_index,
            cbw.week_index
        FROM calendar_book_days cbd
        INNER JOIN calendar_book_weeks cbw
            ON cbw.id = cbd.week_id
        WHERE cbd.id = :day_id
          AND cbd.projection_id = :projection_id
          AND cbw.projection_id = :projection_id
        LIMIT 1
    ");

    $parentStmt->execute([
        ':day_id' => $dayId,
        ':projection_id' => $projectionId,
    ]);

    $parentDay = $parentStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($parentDay)) {
        throw new RuntimeException('calendar_book_day parent not found');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_book_times
        WHERE projection_id = :projection_id
          AND day_id = :day_id
          AND time_index = :time_index
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
        ':day_id' => $dayId,
        ':time_index' => $timeIndex,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($row)) {
        return $row;
    }

    $entityId = calendar_book_time_entity_id(
        $projectionId,
        (int)$parentDay['week_index'],
        (int)$parentDay['day_index'],
        $timeIndex
    );

    ensure_calendar_structure_entity_row(
        $pdo,
        $entityId,
        'entity_type_calendar_time'
    );

    $insert = $pdo->prepare("
        INSERT INTO calendar_book_times (
            projection_id,
            day_id,
            time_index,
            entity_id,
            created_at,
            updated_at
        )
        VALUES (
            :projection_id,
            :day_id,
            :time_index,
            :entity_id,
            NOW(),
            NOW()
        )
    ");

    $insert->execute([
        ':projection_id' => $projectionId,
        ':day_id' => $dayId,
        ':time_index' => $timeIndex,
        ':entity_id' => $entityId,
    ]);

    return reload_calendar_book_time(
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

function reload_calendar_book_time(
    PDO $pdo,
    int $timeId
): array {

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_book_times
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $timeId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('calendar_book_time reload failed');
    }

    return $row;
}

function ensure_calendar_structure_entity_row(
    PDO $pdo,
    string $entityId,
    string $entityTypeId
): void {

    $entityId = trim($entityId);
    $entityTypeId = trim($entityTypeId);

    if ($entityId === '') {
        throw new InvalidArgumentException('entity_id must be non-empty');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('entity_type_id must be non-empty');
    }

    $stmt = $pdo->prepare("
        INSERT INTO entities (
            id,
            entity_type_id,
            lifecycle_state
        )
        VALUES (
            :id,
            :entity_type_id,
            'active'
        )
        ON DUPLICATE KEY UPDATE
            id = id
    ");

    $stmt->execute([
        ':id' => $entityId,
        ':entity_type_id' => $entityTypeId,
    ]);
}

function calendar_book_week_entity_id(
    int $projectionId,
    int $weekIndex
): string {

    return sprintf(
        'calendar_book_week:projection=%d:week=%d',
        $projectionId,
        $weekIndex
    );
}

function calendar_book_day_entity_id(
    int $projectionId,
    int $weekIndex,
    int $dayIndex
): string {

    return sprintf(
        'calendar_book_day:projection=%d:week=%d:day=%d',
        $projectionId,
        $weekIndex,
        $dayIndex
    );
}

function calendar_book_time_entity_id(
    int $projectionId,
    int $weekIndex,
    int $dayIndex,
    int $timeIndex
): string {

    return sprintf(
        'calendar_book_time:projection=%d:week=%d:day=%d:time=%d',
        $projectionId,
        $weekIndex,
        $dayIndex,
        $timeIndex
    );
}
