<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_projection_materializer.php';

/**
 * Canonical mutation boundary for projection-relevant source fields on an
 * existing event-layer calendar event.
 *
 * This boundary exists so that source-event mutations which affect projection
 * materialization also refresh the derived projection surfaces immediately.
 *
 * Allowed writes:
 * - calendar_events.real_date_start_id
 * - calendar_events.real_date_end_id
 * - calendar_events.projection_id
 * - calendar_events.book_time_id
 * - calendar_events.event_index
 *
 * This boundary must not mutate semantic text surfaces or ontology linkage
 * fields. Those belong to the existing metadata / ontology appliers.
 */
function apply_calendar_event_projection_source_fields(
    PDO $pdo,
    string $calendarEventEntityId,
    array $changes
): array {
    $entityId = trim($calendarEventEntityId);

    if ($entityId === '') {
        throw new InvalidArgumentException(
            'Missing calendar event entity_id for projection source apply'
        );
    }

    $allowedFields = [
        'real_date_start_id',
        'real_date_end_id',
        'projection_id',
        'book_time_id',
        'event_index',
    ];

    $changes = array_intersect_key($changes, array_flip($allowedFields));

    if ($changes === []) {
        throw new InvalidArgumentException(
            'No projection-relevant source fields supplied'
        );
    }

    $event = require_calendar_event_projection_source_row($pdo, $entityId);
    $affectedProjectionIds = collect_calendar_event_affected_projection_ids(
        $pdo,
        (int) $event['id'],
        (int) ($event['projection_id'] ?? 0)
    );

    $normalized = normalize_calendar_event_projection_source_changes(
        $changes
    );

    $normalized = apply_inherited_book_time_real_dates_to_projection_changes(
        $pdo,
        $event,
        $normalized
    );

    $assignments = [];
    $params = [
        ':entity_id' => $entityId,
    ];

    foreach ($normalized as $field => $value) {
        $assignments[] = $field . ' = :' . $field;
        $params[':' . $field] = $value;
    }

    $stmt = $pdo->prepare(
        "
        UPDATE calendar_events
        SET " . implode(",\n            ", $assignments) . "
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
        "
    );

    $stmt->execute($params);

    $updatedEvent = require_calendar_event_projection_source_row($pdo, $entityId);
    $affectedProjectionIds = array_values(array_unique(array_merge(
        $affectedProjectionIds,
        collect_calendar_event_affected_projection_ids(
            $pdo,
            (int) $updatedEvent['id'],
            (int) ($updatedEvent['projection_id'] ?? 0)
        )
    )));

    foreach ($affectedProjectionIds as $projectionId) {
        rebuild_calendar_projection($pdo, $projectionId);
    }

    return [
        'calendar_event_id' => (int) $updatedEvent['id'],
        'calendar_event_entity_id' => $entityId,
        'applied_fields' => array_keys($normalized),
        'updated_rows' => $stmt->rowCount(),
        'affected_projection_ids' => $affectedProjectionIds,
        'event' => $updatedEvent,
    ];
}

function require_calendar_event_projection_source_row(
    PDO $pdo,
    string $calendarEventEntityId
): array {
    $stmt = $pdo->prepare(
        "
        SELECT *
        FROM calendar_events
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
        "
    );

    $stmt->execute([
        ':entity_id' => $calendarEventEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'No calendar event found for entity_id: ' . $calendarEventEntityId
        );
    }

    return $row;
}

function collect_calendar_event_affected_projection_ids(
    PDO $pdo,
    int $calendarEventId,
    int $sourceProjectionId = 0
): array {
    $projectionIds = [];

    if ($sourceProjectionId > 0) {
        $projectionIds[] = $sourceProjectionId;
    }

    $stmt = $pdo->prepare(
        "
        SELECT projection_id
        FROM calendar_event_projection_membership
        WHERE calendar_event_id = :calendar_event_id
        "
    );

    $stmt->execute([
        ':calendar_event_id' => $calendarEventId,
    ]);

    $membershipProjectionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($membershipProjectionIds as $projectionId) {
        $projectionId = (int) $projectionId;

        if ($projectionId > 0) {
            $projectionIds[] = $projectionId;
        }
    }

    return array_values(array_unique($projectionIds));
}

function apply_inherited_book_time_real_dates_to_projection_changes(
    PDO $pdo,
    array $event,
    array $changes
): array {
    $currentBookTimeId = (int)($event['book_time_id'] ?? 0);
    $nextBookTimeId = array_key_exists('book_time_id', $changes)
        ? (int)($changes['book_time_id'] ?? 0)
        : $currentBookTimeId;

    if ($nextBookTimeId < 1) {
        return $changes;
    }

    $resolvedDates = resolve_book_time_inherited_real_dates_for_projection_source(
        $pdo,
        $nextBookTimeId
    );

    $currentStartDateId = trim((string)(
        $changes['real_date_start_id']
        ?? $event['real_date_start_id']
        ?? ''
    ));

    $currentEndDateId = trim((string)(
        $changes['real_date_end_id']
        ?? $event['real_date_end_id']
        ?? ''
    ));

    if ($currentStartDateId === '') {
        $changes['real_date_start_id'] = $resolvedDates['real_date_start_id'];
    }

    if ($currentEndDateId === '') {
        $changes['real_date_end_id'] = $resolvedDates['real_date_end_id'];
    }

    return $changes;
}

function resolve_book_time_inherited_real_dates_for_projection_source(
    PDO $pdo,
    int $bookTimeId
): array {
    if ($bookTimeId < 1) {
        throw new InvalidArgumentException('book_time_id must be positive');
    }

    $stmt = $pdo->prepare(
        "
        SELECT
            cbd.real_date_start_id,
            cbd.real_date_end_id
        FROM calendar_book_times cbt
        INNER JOIN calendar_book_days cbd
            ON cbd.id = cbt.day_id
        WHERE cbt.id = :book_time_id
        LIMIT 1
        "
    );

    $stmt->execute([
        ':book_time_id' => $bookTimeId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'Cannot inherit Book event dates from missing calendar_book_time'
        );
    }

    $realDateStartId = trim((string)($row['real_date_start_id'] ?? ''));
    $realDateEndId = trim((string)($row['real_date_end_id'] ?? ''));

    if ($realDateStartId === '') {
        throw new RuntimeException(
            'Book event cannot localize without calendar_book_day.real_date_start_id'
        );
    }

    if ($realDateEndId === '') {
        $realDateEndId = $realDateStartId;
    }

    return [
        'real_date_start_id' => $realDateStartId,
        'real_date_end_id' => $realDateEndId,
    ];
}

function normalize_calendar_event_projection_source_changes(
    array $changes
): array {
    $normalized = [];

    foreach ($changes as $field => $value) {
        if ($field === 'real_date_start_id' || $field === 'real_date_end_id') {
            if ($value === null) {
                $normalized[$field] = null;
                continue;
            }

            $value = trim((string) $value);
            $normalized[$field] = ($value === '') ? null : $value;
            continue;
        }

        if ($value === null || $value === '') {
            $normalized[$field] = null;
            continue;
        }

        $intValue = (int) $value;

        if ($intValue < 1) {
            throw new InvalidArgumentException(
                $field . ' must be a positive integer when provided'
            );
        }

        $normalized[$field] = $intValue;
    }

    return $normalized;
}
