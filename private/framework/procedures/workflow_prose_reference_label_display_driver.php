<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';

function fw_execute_workflow_prose_display_by_reference_label(
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

    $referenceLabel = trim((string)(
        $payload['reference_label'] ?? ''
    ));

    if ($referenceLabel === '') {
        return [
            'success' => false,
            'error' => 'Missing prose reference label',
            'context' => $context,
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            cerl.reference_label,
            cerl.prose_family_id,
            ce.entity_id AS calendar_event_entity_id,
            pp.id AS prose_projection_id,
            pp.published_prose_draft_id,
            pd.entity_id AS prose_draft_entity_id,
            pd.title AS prose_draft_title,
            pd.prose_body
        FROM calendar_event_reference_labels cerl
        INNER JOIN calendar_events ce
            ON ce.id = cerl.calendar_event_id
        INNER JOIN prose_projections pp
            ON pp.prose_family_id = cerl.prose_family_id
           AND pp.target_entity_id = ce.entity_id
           AND pp.role_id = 'prose_projection_role_primary'
           AND pp.projection_type_id = 'projection_type_timeline_view'
        INNER JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
        WHERE cerl.reference_label = :reference_label
        ORDER BY
            pp.is_export_target DESC,
            pp.projection_order ASC,
            pp.id ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':reference_label' => $referenceLabel,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        return [
            'success' => false,
            'error' => 'No published prose found for reference label',
            'context' => $context,
        ];
    }

    return [
        'success' => true,

        'context' => array_merge(
            $context,
            [
                'artifact' => [
                    'reference_label' => $row['reference_label'],
                    'prose_family_id' => (int) $row['prose_family_id'],
                    'calendar_event_entity_id' => $row['calendar_event_entity_id'],
                    'prose_projection_id' => (int) $row['prose_projection_id'],
                    'published_prose_draft_id' => (int) $row['published_prose_draft_id'],
                    'prose_draft_entity_id' => $row['prose_draft_entity_id'],
                    'prose_draft_title' => $row['prose_draft_title'],
                    'assembled_prose' => $row['prose_body'],
                ],
            ]
        ),
    ];
}
