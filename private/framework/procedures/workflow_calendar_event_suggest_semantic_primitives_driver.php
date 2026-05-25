<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_semantic_primitive_deriver.php';
require_once __DIR__ . '/../prose/prose_projection_fetcher.php';

function fw_execute_workflow_calendar_event_suggest_semantic_primitives(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $calendarEventEntityId = trim((string)(
        $input['calendar_event_entity_id']
        ?? $context['calendar_event_entity_id']
        ?? ''
    ));

    if ($calendarEventEntityId === '') {
        return [
            'success' => false,
            'error' => 'Missing calendar_event_entity_id',
            'context' => $context,
        ];
    }

    $projection = fetch_primary_timeline_prose_projection(
        $pdo,
        $calendarEventEntityId
    );

    if (!is_array($projection)) {
        return [
            'success' => false,
            'error' => 'No published prose projection found',
            'calendar_event_entity_id' => $calendarEventEntityId,
            'context' => $context,
        ];
    }

    $proseBody = trim((string)(
        $projection['prose_body'] ?? ''
    ));

    if ($proseBody === '') {
        return [
            'success' => false,
            'error' => 'Published prose body is empty',
            'calendar_event_entity_id' => $calendarEventEntityId,
            'context' => $context,
        ];
    }

    $derivation = derive_semantic_primitives(
        $proseBody,
        [
            'calendar_event' => [
                'entity_id' => $calendarEventEntityId,
            ],
        ]
    );

    return [
        'success' => true,
        'workflow' => 'calendar_event_suggest_semantic_primitives',
        'calendar_event_entity_id' => $calendarEventEntityId,
        'projection' => [
            'prose_projection_id' => $projection['prose_projection_id'] ?? null,
            'prose_draft_id' => $projection['prose_draft_id'] ?? null,
            'prose_entity_id' => $projection['prose_entity_id'] ?? null,
        ],
        'derivation' => $derivation,
        'context' => array_merge(
            $context,
            [
                'semantic_primitive_derivation' => $derivation,
            ]
        ),
    ];
}
