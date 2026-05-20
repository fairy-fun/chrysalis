<?php

declare(strict_types=1);

/**
 * Canonical calendar event projection membership service.
 *
 * Responsibilities:
 *
 * - attach calendar events to projections
 * - ensure projection membership rows
 *
 * This service owns only source association.
 *
 * It must NOT:
 * - reconstruct chronology recursively
 * - infer locality from parent_event_id
 * - mutate canonical Book locality
 * - write calendar_event_projections
 * - write calendar_event_projection_books
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

    require_calendar_event_projection_source(
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

        $results[] = [
            'projection_id' => $projectionId,
            'projection_code'
                => $projection['projection_code'],
            'projection_type_id'
                => $projection['projection_type_id'],
            'membership_id' => $membershipId,
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
