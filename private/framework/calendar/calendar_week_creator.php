<?php

declare(strict_types=1);

/**
 * Create a book-level calendar week event and attach it to the book projection.
 *
 * Core model:
 * calendar_events on calendar_layer_week are attached to books through
 * calendar_event_projections -> calendar_projections.
 */
function create_calendar_week_for_book(
    PDO $pdo,
    string $bookCode,
    int $weekIndex,
    string $weekLabel,
    string $realDateStartId
): array {
    $bookCode = trim($bookCode);
    $weekLabel = trim($weekLabel);
    $realDateStartId = trim($realDateStartId);

    if ($bookCode === '') {
        throw new InvalidArgumentException('book_code must be a non-empty string');
    }

    if ($weekIndex < 1) {
        throw new InvalidArgumentException('week_index must be a positive integer');
    }

    if ($weekLabel === '') {
        throw new InvalidArgumentException('week_label must be a non-empty string');
    }

    if ($realDateStartId === '') {
        throw new InvalidArgumentException('real_date_start_id must be a non-empty string');
    }

    $pdo->beginTransaction();

    try {
        $book = read_calendar_week_book($pdo, $bookCode);
        if ($book === null) {
            throw new RuntimeException('Book not found for book_code=' . $bookCode);
        }

        validate_calendar_date_id($pdo, $realDateStartId);

        $projectionCode = 'book_projection_' . $bookCode;

        if (calendar_week_exists_for_book_projection($pdo, $projectionCode, $weekIndex)) {
            throw new RuntimeException('Week already exists for book_code=' . $bookCode . ' week_index=' . $weekIndex);
        }

        // step 1: create week event
        $eventStmt = $pdo->prepare(
            "INSERT INTO sxnzlfun_chrysalis.calendar_events (
                event_id,
                layer_id,
                summary,
                week_index,
                day_index,
                time_index,
                real_date_start_id,
                created_at,
                updated_at
            ) VALUES (
                UNIX_TIMESTAMP(),
                'calendar_layer_week',
                :summary,
                :week_index,
                NULL,
                NULL,
                :real_date_start_id,
                NOW(),
                NOW()
            )"
        );

        $summary = 'Week ' . $weekIndex . ' — ' . $weekLabel;
        $eventStmt->execute([
            ':summary' => $summary,
            ':week_index' => $weekIndex,
            ':real_date_start_id' => $realDateStartId,
        ]);

        $calendarEventId = (int)$pdo->lastInsertId();
        if ($calendarEventId < 1) {
            throw new RuntimeException('Week event insert did not return a calendar event id');
        }

        // step 2: ensure projection
        $projectionStmt = $pdo->prepare(
            "INSERT INTO sxnzlfun_chrysalis.calendar_projections (
                projection_type_id,
                projection_code,
                projection_title,
                book_id,
                created_at,
                updated_at
            ) VALUES (
                'projection_type_book',
                :projection_code,
                :projection_title,
                :book_id,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                projection_title = VALUES(projection_title),
                book_id = VALUES(book_id),
                updated_at = NOW()"
        );

        $projectionStmt->execute([
            ':projection_code' => $projectionCode,
            ':projection_title' => (string)$book['book_title'],
            ':book_id' => $book['id'],
        ]);

        $projectionId = read_calendar_projection_id($pdo, $projectionCode);
        if ($projectionId === null) {
            throw new RuntimeException('Book projection could not be resolved after upsert');
        }

        // step 3: link event
        $linkStmt = $pdo->prepare(
            "INSERT INTO sxnzlfun_chrysalis.calendar_event_projections (
                calendar_event_id,
                calendar_projection_id,
                chronology_address,
                projection_sequence,
                created_at,
                updated_at
            ) VALUES (
                :calendar_event_id,
                :calendar_projection_id,
                'PRIMARY',
                1,
                NOW(),
                NOW()
            )"
        );

        $linkStmt->execute([
            ':calendar_event_id' => $calendarEventId,
            ':calendar_projection_id' => $projectionId,
        ]);

        $pdo->commit();

        return [
            'calendar_event_id' => $calendarEventId,
            'calendar_projection_id' => $projectionId,
            'projection_code' => $projectionCode,
            'book_code' => $bookCode,
            'week_index' => $weekIndex,
            'summary' => $summary,
            'real_date_start_id' => $realDateStartId,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function read_calendar_week_book(PDO $pdo, string $bookCode): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, book_code, book_title
         FROM sxnzlfun_chrysalis.books
         WHERE book_code = :book_code
         LIMIT 1"
    );

    $stmt->execute([
        ':book_code' => $bookCode,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function validate_calendar_date_id(PDO $pdo, string $dateId): void
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM sxnzlfun_chrysalis.dates
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->execute([
        ':id' => $dateId,
    ]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('Invalid real_date_start_id: no matching dates.id=' . $dateId);
    }
}

function read_calendar_projection_id(PDO $pdo, string $projectionCode): ?int
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM sxnzlfun_chrysalis.calendar_projections
         WHERE projection_code = :projection_code
         LIMIT 1"
    );

    $stmt->execute([
        ':projection_code' => $projectionCode,
    ]);

    $value = $stmt->fetchColumn();

    return $value === false ? null : (int)$value;
}

function calendar_week_exists_for_book_projection(PDO $pdo, string $projectionCode, int $weekIndex): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM sxnzlfun_chrysalis.calendar_events ce
         JOIN sxnzlfun_chrysalis.calendar_event_projections cep
           ON cep.calendar_event_id = ce.id
         JOIN sxnzlfun_chrysalis.calendar_projections cp
           ON cp.id = cep.calendar_projection_id
         WHERE cp.projection_code = :projection_code
           AND ce.layer_id = 'calendar_layer_week'
           AND ce.week_index = :week_index
         LIMIT 1"
    );

    $stmt->execute([
        ':projection_code' => $projectionCode,
        ':week_index' => $weekIndex,
    ]);

    return $stmt->fetchColumn() !== false;
}
