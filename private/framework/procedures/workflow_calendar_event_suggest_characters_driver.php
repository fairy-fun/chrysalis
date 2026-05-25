<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_character_suggester.php';
require_once __DIR__ . '/workflow_value_resolver.php';

function fw_execute_workflow_calendar_event_suggest_characters(
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

    $entityId = trim((string)(
        $payload['calendar_event_entity_id']
        ?? $context['calendar_event']['entity_id']
        ?? ''
    ));

    if ($entityId === '') {
        throw new RuntimeException(
            'Missing calendar_event_entity_id for character suggestion workflow'
        );
    }

    $proseStmt = $pdo->prepare("
        SELECT
            pp.id AS prose_projection_id,
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.prose_body
        FROM prose_projections pp
        INNER JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
        WHERE pp.target_entity_id = :entity_id
          AND pp.published_prose_draft_id IS NOT NULL
          AND pp.role_id = 'prose_projection_role_primary'
          AND pp.projection_type_id = 'projection_type_timeline_view'
        ORDER BY
            pp.projection_order ASC,
            pp.id ASC
        LIMIT 1
    ");

    $proseStmt->execute([
        ':entity_id' => $entityId,
    ]);

    $proseRow = $proseStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($proseRow)) {
        throw new RuntimeException(
            'No prose attached to calendar event: ' . $entityId
        );
    }

    $proseBody = trim((string)($proseRow['prose_body'] ?? ''));

    if ($proseBody === '') {
        throw new RuntimeException(
            'Attached prose draft has empty prose_body'
        );
    }

    $suggestions = suggest_prose_characters(
        $pdo,
        $proseBody,
        [
            'calendar_event_entity_id' => $entityId,
            'prose_projection_id' => (int)$proseRow['prose_projection_id'],
            'prose_entity_id' => (string)$proseRow['prose_entity_id'],
        ]
    );

    return [
        'success' => true,
        'status' => 'ok',
        'workflow' => 'calendar_event_suggest_characters',
        'tier' => 3,
        'entity_id' => $entityId,
        'context' => array_merge(
            $context,
            [
                'character_suggestions' => $suggestions,
            ]
        ),
    ];
}
