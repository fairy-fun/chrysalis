<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_character_suggester.php';
require_once __DIR__ . '/workflow_value_resolver.php';

function fw_workflow_normalize_character_tag_approval(
    mixed $approval
): string {

    $value = mb_strtolower(trim((string)$approval));

    if (in_array($value, ['yes', 'y', 'approve', 'approved', 'apply', 'tag them'], true)) {
        return 'approved';
    }

    if (in_array($value, ['no', 'n', 'reject', 'rejected', 'cancel', 'do not apply'], true)) {
        return 'rejected';
    }

    return 'unrecognised';
}

function fw_workflow_fetch_calendar_event_attached_prose(
    PDO $pdo,
    string $calendarEventEntityId
): array {

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
        ':entity_id' => $calendarEventEntityId,
    ]);

    $proseRow = $proseStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($proseRow)) {
        throw new RuntimeException(
            'No prose attached to calendar event: ' . $calendarEventEntityId
        );
    }

    $proseBody = trim((string)($proseRow['prose_body'] ?? ''));

    if ($proseBody === '') {
        throw new RuntimeException(
            'Attached prose draft has empty prose_body'
        );
    }

    $proseRow['prose_body'] = $proseBody;

    return $proseRow;
}

function fw_execute_workflow_calendar_event_prepare_character_tag_approval(
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
            'Missing calendar_event_entity_id for character tag approval workflow'
        );
    }

    $proseRow = fw_workflow_fetch_calendar_event_attached_prose(
        $pdo,
        $entityId
    );

    $suggestions = suggest_prose_characters(
        $pdo,
        (string)$proseRow['prose_body'],
        [
            'calendar_event_entity_id' => $entityId,
            'prose_projection_id' => (int)$proseRow['prose_projection_id'],
            'prose_entity_id' => (string)$proseRow['prose_entity_id'],
        ]
    );

    $resolvedSuggestions = [];

    foreach (($suggestions['suggestions']['characters'] ?? []) as $suggestion) {
        if (($suggestion['resolution_status'] ?? null) !== 'resolved') {
            continue;
        }

        if (trim((string)($suggestion['resolved_entity_id'] ?? '')) === '') {
            continue;
        }

        $resolvedSuggestions[] = $suggestion;
    }

    return [
        'success' => $resolvedSuggestions !== [],
        'status' => $resolvedSuggestions === [] ? 'no_resolved_suggestions' : 'ok',
        'workflow' => 'calendar_event_approve_character_tags',
        'tier' => 3,
        'entity_id' => $entityId,
        'context' => array_merge(
            $context,
            [
                'character_tag_approval' => [
                    'calendar_event_entity_id' => $entityId,
                    'prose_projection_id' => (int)$proseRow['prose_projection_id'],
                    'prose_entity_id' => (string)$proseRow['prose_entity_id'],
                    'resolved_suggestions' => $resolvedSuggestions,
                    'mutates_character_ontology' => false,
                    'approval_required' => true,
                ],
            ]
        ),
    ];
}

function fw_execute_workflow_calendar_event_apply_character_tags(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $approval = fw_workflow_normalize_character_tag_approval(
        $input['character_tag_approval'] ?? ''
    );

    if ($approval !== 'approved') {
        return [
            'success' => false,
            'status' => $approval,
            'workflow' => 'calendar_event_approve_character_tags',
            'transition_reason' => 'approval_not_granted',
            'context' => $context,
        ];
    }

    $approvalContext = $context['character_tag_approval'] ?? [];

    if (!is_array($approvalContext)) {
        throw new RuntimeException('Missing character tag approval context');
    }

    $calendarEventEntityId = trim((string)($approvalContext['calendar_event_entity_id'] ?? ''));
    $proseProjectionId = (int)($approvalContext['prose_projection_id'] ?? 0);
    $proseEntityId = trim((string)($approvalContext['prose_entity_id'] ?? ''));
    $resolvedSuggestions = $approvalContext['resolved_suggestions'] ?? [];

    if ($calendarEventEntityId === '') {
        throw new RuntimeException('Missing calendar_event_entity_id in character tag approval context');
    }

    if (!is_array($resolvedSuggestions) || $resolvedSuggestions === []) {
        throw new RuntimeException('No resolved character suggestions available to approve');
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO calendar_event_character_tags (
            calendar_event_entity_id,
            character_entity_id,
            prose_projection_id,
            prose_entity_id,
            candidate_label,
            surface_forms_json,
            evidence_json,
            confidence,
            approval_status,
            source,
            created_at,
            updated_at
        ) VALUES (
            :calendar_event_entity_id,
            :character_entity_id,
            :prose_projection_id,
            :prose_entity_id,
            :candidate_label,
            :surface_forms_json,
            :evidence_json,
            :confidence,
            'approved',
            'deterministic_character_suggestion',
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            prose_projection_id = VALUES(prose_projection_id),
            prose_entity_id = VALUES(prose_entity_id),
            candidate_label = VALUES(candidate_label),
            surface_forms_json = VALUES(surface_forms_json),
            evidence_json = VALUES(evidence_json),
            confidence = VALUES(confidence),
            approval_status = 'approved',
            source = VALUES(source),
            updated_at = NOW()
    ");

    $approvedTags = [];

    foreach ($resolvedSuggestions as $suggestion) {
        if (!is_array($suggestion)) {
            continue;
        }

        $characterEntityId = trim((string)($suggestion['resolved_entity_id'] ?? ''));

        if ($characterEntityId === '') {
            continue;
        }

        $surfaceFormsJson = json_encode(
            $suggestion['surface_forms'] ?? [],
            JSON_THROW_ON_ERROR
        );

        $evidenceJson = json_encode(
            $suggestion['evidence'] ?? [],
            JSON_THROW_ON_ERROR
        );

        $insertStmt->execute([
            ':calendar_event_entity_id' => $calendarEventEntityId,
            ':character_entity_id' => $characterEntityId,
            ':prose_projection_id' => $proseProjectionId,
            ':prose_entity_id' => $proseEntityId,
            ':candidate_label' => $suggestion['candidate_label'] ?? null,
            ':surface_forms_json' => $surfaceFormsJson,
            ':evidence_json' => $evidenceJson,
            ':confidence' => (float)($suggestion['confidence'] ?? 0),
        ]);

        $approvedTags[] = [
            'calendar_event_entity_id' => $calendarEventEntityId,
            'character_entity_id' => $characterEntityId,
            'candidate_label' => $suggestion['candidate_label'] ?? null,
            'surface_forms' => $suggestion['surface_forms'] ?? [],
            'approval_status' => 'approved',
        ];
    }

    return [
        'success' => $approvedTags !== [],
        'status' => $approvedTags === [] ? 'no_tags_applied' : 'ok',
        'workflow' => 'calendar_event_approve_character_tags',
        'tier' => 3,
        'entity_id' => $calendarEventEntityId,
        'context' => array_merge(
            $context,
            [
                'approved_character_tags' => $approvedTags,
            ]
        ),
    ];
}
