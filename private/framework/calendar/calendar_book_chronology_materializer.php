<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_book_chronology_ensurer.php';

function ensure_calendar_book_week(
    PDO $pdo,
    int $projectionId,
    int $weekIndex
): array {

    assert_calendar_book_projection_exists(
        $pdo,
        $projectionId
    );

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

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($existing)) {
        return $existing;
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

    $id = (int)$pdo->lastInsertId();

    $reload = $pdo->prepare("
        SELECT *
        FROM calendar_book_weeks
        WHERE id = :id
        LIMIT 1
    ");

    $reload->execute([
        ':id' => $id,
    ]);

    return $reload->fetch(PDO::FETCH_ASSOC);
}