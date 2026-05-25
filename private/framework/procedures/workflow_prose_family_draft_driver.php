<?php
declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_family_draft_creator.php';
require_once __DIR__ . '/workflow_value_resolver.php';

function fw_execute_workflow_prose_create_family_draft(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $result = create_prose_family_draft(
        $pdo,
        $payload
    );

    return [
        'success' => true,
        'context' => array_merge(
            $context,
            [
                'created_prose' => $result,
            ]
        ),
    ];
}

function fw_execute_workflow_prose_resolve_calendar_event_family(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $eventReference = trim((string)(
        $payload['calendar_event_reference'] ?? ''
    ));

    if ($eventReference === '') {
        return [
            'success' => false,
            'context' => $context,
        ];
    }

    $calendarEvent = prose_family_workflow_resolve_calendar_event(
        $pdo,
        $eventReference
    );

    if ($calendarEvent === null) {
        return [
            'success' => false,
            'context' => $context,
        ];
    }

    $stmt = $pdo->prepare('
        SELECT
            pf.id,
            pf.entity_id,
            pp.id AS prose_projection_id,
            pp.published_prose_draft_id
        FROM prose_projections pp
        INNER JOIN prose_families pf
            ON pf.id = pp.prose_family_id
        WHERE pp.target_entity_id = :target_entity_id
        LIMIT 1
    ');

    $stmt->execute([
        ':target_entity_id' => $calendarEvent['entity_id'],
    ]);

    $proseFamily = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($proseFamily)) {
        return [
            'success' => false,
            'context' => array_merge(
                $context,
                [
                    'calendar_event' => $calendarEvent,
                ]
            ),
        ];
    }

    return [
        'success' => true,
        'context' => array_merge(
            $context,
            [
                'calendar_event' => $calendarEvent,
                'prose_family' => $proseFamily,
            ]
        ),
    ];
}

function prose_family_workflow_resolve_calendar_event(
    PDO $pdo,
    string $reference
): ?array {

    $reference = trim($reference);

    if (preg_match('/^calendar_event:[0-9]+$/', $reference) === 1) {
        return prose_family_workflow_fetch_event_by_entity_id(
            $pdo,
            $reference
        );
    }

    if (preg_match('/^[0-9]+$/', $reference) === 1) {
        return prose_family_workflow_fetch_event_by_entity_id(
            $pdo,
            'calendar_event:' . $reference
        );
    }

    if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $reference) === 1) {
        return prose_family_workflow_fetch_event_by_address(
            $pdo,
            $reference
        );
    }

    return null;
}

function prose_family_workflow_fetch_event_by_entity_id(
    PDO $pdo,
    string $entityId
): ?array {

    $stmt = $pdo->prepare('
        SELECT
            id,
            entity_id,
            projection_id,
            book_time_id,
            event_index,
            subevent_index,
            layer_id,
            summary
        FROM calendar_events
        WHERE entity_id = :entity_id
        LIMIT 1
    ');

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function prose_family_workflow_fetch_event_by_address(
    PDO $pdo,
    string $address
): ?array {

    $parts = array_map('intval', explode('.', $address));

    if (count($parts) !== 4) {
        return null;
    }

    [$weekIndex, $dayIndex, $timeIndex, $eventIndex] = $parts;

    $stmt = $pdo->prepare('
        SELECT
            ce.id,
            ce.entity_id,
            ce.projection_id,
            ce.book_time_id,
            ce.event_index,
            ce.subevent_index,
            ce.layer_id,
            ce.summary
        FROM calendar_events ce
        INNER JOIN calendar_book_times cbt
            ON cbt.id = ce.book_time_id
        INNER JOIN calendar_book_days cbd
            ON cbd.id = cbt.day_id
        INNER JOIN calendar_book_weeks cbw
            ON cbw.id = cbd.week_id
        WHERE cbw.week_index = :week_index
          AND cbd.day_index = :day_index
          AND cbt.time_index = :time_index
          AND ce.event_index = :event_index
          AND ce.parent_event_id IS NULL
        LIMIT 2
    ');

    $stmt->execute([
        ':week_index' => $weekIndex,
        ':day_index' => $dayIndex,
        ':time_index' => $timeIndex,
        ':event_index' => $eventIndex,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== 1) {
        return null;
    }

    return $rows[0];
}
