<?php

declare(strict_types=1);

/**
 * Canonical calendar event projection membership/materialization service.
 *
 * Responsibilities:
 *
 * - attach calendar events to projections
 * - ensure projection membership rows
 * - ensure projection render rows
 * - ensure Book projection metadata rows
 *
 * This service is intentionally projection-aware.
 *
 * It must NOT:
 * - reconstruct chronology recursively
 * - infer locality from parent_event_id
 * - mutate canonical Book locality
 */

function ensure_calendar_event_projection_memberships(
    PDO $pdo,
    int $calendarEventId,
    array $projectionIds
): array {

    if ($calendarEventId < 1) {
        throw new InvalidArgumentException(
            'calendarEventId must be positive'
        );
    }

    $projectionIds = array_values(
        array_unique(
            array_map(
                'intval',
                $projectionIds
            )
        )
    );

    if ($projectionIds === []) {
        throw new InvalidArgumentException(
            'At least one projection_id is required'
        );
    }

    $event = require_calendar_event_projection_source(
        $pdo,
        $calendarEventId
    );

    $results = [];

    foreach ($projectionIds as $projectionId) {

        if ($projectionId < 1) {
            throw new RuntimeException(
                'projection_id must be positive'
            );
        }

        $projection = require_calendar_projection_row(
            $pdo,
            $projectionId
        );

        $membershipId =
            ensure_calendar_event_projection_membership(
                $pdo,
                $calendarEventId,
                $projectionId
            );

        $projectionRowId =
            ensure_calendar_event_projection_row(
                $pdo,
                $event,
                $projection
            );

        if (
            ($projection['projection_type_id'] ?? null)
            === 'projection_type_book'
        ) {
            ensure_calendar_event_projection_book_row(
                $pdo,
                $projectionRowId,
                $event
            );
        }

        $results[] = [
            'projection_id' => $projectionId,
            'projection_code'
                => $projection['projection_code'],
            'projection_type_id'
                => $projection['projection_type_id'],
            'membership_id' => $membershipId,
            'projection_row_id' => $projectionRowId,
        ];
    }

    return $results;
}

function ensure_calendar_event_projection_membership(
    PDO $pdo,
    int $calendarEventId,
    int $projectionId
): int {

    $stmt = $pdo->prepare("
        SELECT id
        FROM calendar_event_projection_membership
        WHERE calendar_event_id = :calendar_event_id
          AND projection_id = :projection_id
        LIMIT 1
    ");

    $stmt->execute([
        ':calendar_event_id' => $calendarEventId,
        ':projection_id' => $projectionId,
    ]);

    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        return (int)$existingId;
    }

    $insert = $pdo->prepare("
        INSERT INTO calendar_event_projection_membership (
            calendar_event_id,
            projection_id
        ) VALUES (
            :calendar_event_id,
            :projection_id
        )
    ");

    $insert->execute([
        ':calendar_event_id' => $calendarEventId,
        ':projection_id' => $projectionId,
    ]);

    return (int)$pdo->lastInsertId();
}

function ensure_calendar_event_projection_row(
    PDO $pdo,
    array $event,
    array $projection
): int {

    $calendarEventId = (int)($event['id'] ?? 0);
    $projectionId = (int)($projection['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT id
        FROM calendar_event_projections
        WHERE calendar_event_id = :calendar_event_id
          AND calendar_projection_id = :projection_id
        LIMIT 1
    ");

    $stmt->execute([
        ':calendar_event_id' => $calendarEventId,
        ':projection_id' => $projectionId,
    ]);

    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        return (int)$existingId;
    }

    $chronologyAddress =
        trim((string)($event['chronology_address'] ?? ''));

    if ($chronologyAddress === '') {
        $chronologyAddress = null;
    }

    $insert = $pdo->prepare("
        INSERT INTO calendar_event_projections (
            calendar_event_id,
            calendar_projection_id,
            chronology_address,
            projection_sequence,
            created_at,
            updated_at
        ) VALUES (
            :calendar_event_id,
            :projection_id,
            :chronology_address,
            :projection_sequence,
            NOW(),
            NOW()
        )
    ");

    $insert->execute([
        ':calendar_event_id' => $calendarEventId,
        ':projection_id' => $projectionId,
        ':chronology_address' => $chronologyAddress,
        ':projection_sequence'
            => (int)($event['event_index'] ?? 1),
    ]);

    return (int)$pdo->lastInsertId();
}

function ensure_calendar_event_projection_book_row(
    PDO $pdo,
    int $calendarEventProjectionId,
    array $event
): void {

    $stmt = $pdo->prepare("
        SELECT calendar_event_projection_id
        FROM calendar_event_projection_books
        WHERE calendar_event_projection_id
            = :calendar_event_projection_id
        LIMIT 1
    ");

    $stmt->execute([
        ':calendar_event_projection_id'
            => $calendarEventProjectionId,
    ]);

    $existing = $stmt->fetchColumn();

    if ($existing !== false) {
        return;
    }

    $insert = $pdo->prepare("
        INSERT INTO calendar_event_projection_books (
            calendar_event_projection_id,
            page_number
        ) VALUES (
            :calendar_event_projection_id,
            :page_number
        )
    ");

    $insert->execute([
        ':calendar_event_projection_id'
            => $calendarEventProjectionId,

        ':page_number' => null,
    ]);
}

function require_calendar_event_projection_source(
    PDO $pdo,
    int $calendarEventId
): array {

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_events
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $calendarEventId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'calendar_event not found'
        );
    }

    return $row;
}

function require_calendar_projection_row(
    PDO $pdo,
    int $projectionId
): array {

    $stmt = $pdo->prepare("
        SELECT *
        FROM calendar_projections
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $projectionId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'calendar projection not found'
        );
    }

    return $row;
}
