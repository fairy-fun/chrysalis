<?php
declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_family_draft_creator.php';
require_once __DIR__ . '/../prose/prose_projection_publisher.php';
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

function fw_execute_workflow_prose_create_calendar_event_family_draft(
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

    $result = create_calendar_event_prose_family_draft(
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

function fw_execute_workflow_prose_set_projection_published_draft(
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

    $result = set_projection_published_draft(
        $pdo,
        $payload
    );

    return [
        'success' => true,
        'context' => array_merge(
            $context,
            [
                'publication_update' => $result,
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

    $proseFamilyId = prose_family_workflow_extract_family_scope(
        $payload,
        $context
    );

    $calendarEvent = prose_family_workflow_resolve_calendar_event(
        $pdo,
        $eventReference,
        $proseFamilyId
    );

    if ($calendarEvent === null) {
        return [
            'success' => false,
            'context' => $context,
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            pf.id,
            pf.entity_id,
            pp.id AS prose_projection_id,
            pp.published_prose_draft_id,
            pp.is_export_target,
            pp.projection_order,
            pp.role_id,
            pp.projection_type_id
        FROM prose_projections pp
        INNER JOIN prose_families pf
            ON pf.id = pp.prose_family_id
        WHERE pp.target_entity_id = :target_entity_id
          AND pp.role_id = 'prose_projection_role_primary'
          AND pp.projection_type_id = 'projection_type_timeline_view'
        ORDER BY
            pp.is_export_target DESC,
            pp.projection_order ASC,
            pp.id ASC
        LIMIT 1
    ");

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

    $proseFamily['projection_resolution_notes'] = (int)($proseFamily['is_export_target'] ?? 0) === 1
        ? 'Resolved existing export-capable primary timeline projection.'
        : 'Resolved primary timeline projection that is not yet export-capable; publication workflow may explicitly activate export capability.';

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

function prose_family_workflow_extract_family_scope(
    array $payload,
    array $context
): ?int {

    $candidates = [
        $payload['prose_family_id'] ?? null,
        $context['prose_family']['id'] ?? null,
        $context['created_prose']['prose_family']['id'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_int($candidate) && $candidate > 0) {
            return $candidate;
        }

        if (is_string($candidate) && preg_match('/^[0-9]+$/', $candidate) === 1) {
            $id = (int) $candidate;

            if ($id > 0) {
                return $id;
            }
        }
    }

    return null;
}

function prose_family_workflow_resolve_calendar_event(
    PDO $pdo,
    string $reference,
    ?int $proseFamilyId = null
): ?array {

    $reference = trim($reference);

    if ($reference === '') {
        return null;
    }

    if (preg_match('/^calendar_event:[0-9]+$/', $reference) === 1) {
        return prose_family_workflow_fetch_event_by_entity_id(
            $pdo,
            $reference
        );
    }

    if (preg_match('/^[0-9]+$/', $reference) === 1) {
        return prose_family_workflow_fetch_event_by_pk(
            $pdo,
            (int) $reference
        );
    }

    $canonicalEvent = prose_family_workflow_fetch_event_by_entity_id(
        $pdo,
        $reference
    );

    if ($canonicalEvent !== null) {
        return $canonicalEvent;
    }

    if ($proseFamilyId !== null) {
        $labelEvent = resolve_calendar_event_by_reference_label(
            $pdo,
            $proseFamilyId,
            $reference
        );

        if ($labelEvent !== null) {
            return $labelEvent;
        }
    }

    if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $reference) === 1) {
        return prose_family_workflow_fetch_event_by_address(
            $pdo,
            $reference
        );
    }

    return null;
}

function resolve_calendar_event_by_reference_label(
    PDO $pdo,
    int $proseFamilyId,
    string $referenceLabel
): ?array {

    if ($proseFamilyId < 1) {
        throw new InvalidArgumentException('proseFamilyId must be positive');
    }

    $referenceLabel = trim($referenceLabel);

    if ($referenceLabel === '') {
        throw new InvalidArgumentException('referenceLabel must be non-empty');
    }

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
        FROM calendar_event_reference_labels cerl
        INNER JOIN calendar_events ce
            ON ce.id = cerl.calendar_event_id
        WHERE cerl.prose_family_id = :prose_family_id
          AND cerl.reference_label = :reference_label
        LIMIT 1
    ');

    $stmt->execute([
        ':prose_family_id' => $proseFamilyId,
        ':reference_label' => $referenceLabel,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function require_calendar_event_by_reference_label(
    PDO $pdo,
    int $proseFamilyId,
    string $referenceLabel
): array {

    $calendarEvent = resolve_calendar_event_by_reference_label(
        $pdo,
        $proseFamilyId,
        $referenceLabel
    );

    if ($calendarEvent === null) {
        throw new RuntimeException(
            'No calendar event reference label found in prose family scope: ' .
            $referenceLabel
        );
    }

    return $calendarEvent;
}

function prose_family_workflow_fetch_event_by_pk(
    PDO $pdo,
    int $id
): ?array {

    if ($id < 1) {
        return null;
    }

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
        WHERE id = :id
        LIMIT 1
    ');

    $stmt->execute([
        ':id' => $id,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
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
