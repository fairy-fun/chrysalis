<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';

function fw_execute_workflow_calendar_display_day_prose(
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

    $realDateStartId = $payload['real_date_start_id'] ?? null;

    if (!is_string($realDateStartId) || trim($realDateStartId) === '') {
        return [
            'success' => false,
            'error' => 'Missing real_date_start_id for calendar day prose display',
            'context' => $context,
        ];
    }

    $artifact = fw_display_calendar_day_prose(
        $pdo,
        trim($realDateStartId)
    );

    return [
        'success' => ($artifact['item_count'] > 0),
        'status' => ($artifact['item_count'] > 0) ? 'ok' : 'empty',
        'workflow' => 'calendar_day_display_prose',
        'tier' => 2,
        'real_date_start_id' => trim($realDateStartId),
        'context' => array_merge(
            $context,
            [
                'artifact' => $artifact,
            ]
        ),
    ];
}

function fw_display_calendar_day_prose(
    PDO $pdo,
    string $realDateStartId
): array {

    $stmt = $pdo->prepare("
        SELECT
            child.id,
            child.entity_id,
            child.parent_event_id,
            child.layer_id,
            child.summary,
            child.prose_body,
            child.notes,
            child.real_date_start_id,
            child.sequence_index,
            child.subevent_index,
            child.event_index,
            child.time_index,
            child.chronology_address,
            parent.entity_id AS parent_entity_id,
            parent.summary AS parent_summary,
            COALESCE(
                child.real_date_start_id,
                parent.real_date_start_id
            ) AS effective_day_id
        FROM calendar_events child
        LEFT JOIN calendar_events parent
            ON parent.id = child.parent_event_id
           AND child.layer_id = 'calendar_layer_subevent'
        WHERE COALESCE(
                child.real_date_start_id,
                parent.real_date_start_id
            ) = :real_date_start_id
          AND (
                child.prose_body IS NOT NULL
                OR child.notes IS NOT NULL
            )
        ORDER BY
            COALESCE(
                parent.event_index,
                child.event_index
            ) ASC,
            CASE
                WHEN child.layer_id = 'calendar_layer_event'
                THEN 0
                ELSE 1
            END ASC,
            child.subevent_index ASC,
            child.sequence_index ASC,
            child.id ASC
    ");

    $stmt->execute([
        ':real_date_start_id' => $realDateStartId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $assembledProse = [];

    foreach ($rows as $row) {
        $proseBody = trim((string)($row['prose_body'] ?? ''));
        $notes = trim((string)($row['notes'] ?? ''));

        if ($proseBody === '' && $notes === '') {
            continue;
        }

        $item = [
            'id' => (int)$row['id'],
            'entity_id' => (string)$row['entity_id'],
            'parent_event_id' => $row['parent_event_id'],
            'parent_entity_id' => $row['parent_entity_id'],
            'parent_summary' => $row['parent_summary'],
            'layer_id' => (string)$row['layer_id'],
            'summary' => (string)($row['summary'] ?? ''),
            'chronology_address' => $row['chronology_address'],
            'real_date_start_id' => $row['real_date_start_id'],
            'effective_day_id' => (string)$row['effective_day_id'],
            'event_index' => $row['event_index'],
            'time_index' => $row['time_index'],
            'subevent_index' => $row['subevent_index'],
            'sequence_index' => $row['sequence_index'],
            'prose_body' => $proseBody,
            'notes' => $notes,
        ];

        $items[] = $item;

        if ($proseBody !== '') {
            $assembledProse[] = $proseBody;
        } elseif ($notes !== '') {
            $assembledProse[] = $notes;
        }
    }

    return [
        'type' => 'calendar_day_prose',
        'real_date_start_id' => $realDateStartId,
        'item_count' => count($items),
        'items' => $items,
        'assembled_prose' => implode("\n\n", $assembledProse),
    ];
}
