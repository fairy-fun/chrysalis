<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_character_suggester.php';
require_once __DIR__ . '/workflow_value_resolver.php';

function fw_workflow_character_tag_approval_status(
    mixed $approval
): string {

    $value = mb_strtolower(trim((string)$approval));

    if (in_array($value, ['yes', 'y', 'approve', 'approved', 'apply'], true)) {
        return 'approved';
    }

    if (preg_match('/char-[a-z0-9\-]+/i', $value) === 1) {
        return 'partial';
    }

    if (
        str_contains($value, 'except')
        || str_contains($value, 'not ')
        || str_contains($value, 'exclude')
    ) {
        return 'partial';
    }

    if (in_array($value, ['no', 'n', 'reject', 'rejected', 'cancel'], true)) {
        return 'rejected';
    }

    return 'unrecognised';
}

function fw_extract_character_entity_ids(string $text): array
{
    preg_match_all(
        '/CHAR-[A-Z0-9\-]+/i',
        $text,
        $matches
    );

    $entityIds = [];

    foreach (($matches[0] ?? []) as $match) {
        $entityIds[] = strtoupper(trim((string)$match));
    }

    return array_values(array_unique($entityIds));
}

function fw_filter_approved_character_suggestions(
    array $resolvedSuggestions,
    string $approvalInput
): array {

    $approvalInput = trim($approvalInput);

    if ($approvalInput === '') {
        return [];
    }

    $status = fw_workflow_character_tag_approval_status($approvalInput);

    if ($status === 'approved') {
        return $resolvedSuggestions;
    }

    if ($status === 'rejected' || $status === 'unrecognised') {
        return [];
    }

    $mentionedEntityIds = fw_extract_character_entity_ids($approvalInput);

    if ($mentionedEntityIds === []) {
        return [];
    }

    $isExclusion = (
        str_contains(mb_strtolower($approvalInput), 'except')
        || str_contains(mb_strtolower($approvalInput), 'not ')
        || str_contains(mb_strtolower($approvalInput), 'exclude')
    );

    $filtered = [];

    foreach ($resolvedSuggestions as $suggestion) {
        if (!is_array($suggestion)) {
            continue;
        }

        $entityId = strtoupper(trim((string)(
            $suggestion['resolved_entity_id'] ?? ''
        )));

        if ($entityId === '') {
            continue;
        }

        $mentioned = in_array($entityId, $mentionedEntityIds, true);

        if ($isExclusion && !$mentioned) {
            $filtered[] = $suggestion;
            continue;
        }

        if (!$isExclusion && $mentioned) {
            $filtered[] = $suggestion;
        }
    }

    return $filtered;
}

function fw_resolve_calendar_event_persistence_id(
    PDO $pdo,
    string $calendarEventEntityId
): int {

    $stmt = $pdo->prepare("
        SELECT id
        FROM calendar_events
        WHERE entity_id = :entity_id
           OR entity_id = CONCAT('calendar_event:', :bare_entity_id)
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $calendarEventEntityId,
        ':bare_entity_id' => $calendarEventEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'Cannot approve character tags for missing calendar event: ' . $calendarEventEntityId
        );
    }

    $eventId = (int)($row['id'] ?? 0);

    if ($eventId < 1) {
        throw new RuntimeException(
            'Calendar event resolved to invalid persistence id: ' . $calendarEventEntityId
        );
    }

    return $eventId;
}

function fw_validate_character_entity(PDO $pdo, string $entityId): void
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM entities
        WHERE id = :entity_id
          AND entity_type_id = 'entity_type_character'
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    if (!is_array($stmt->fetch(PDO::FETCH_ASSOC))) {
        throw new RuntimeException(
            'Cannot approve non-character or missing entity as participant: ' . $entityId
        );
    }
}

function fw_fetch_calendar_event_attached_prose_for_character_approval(
    PDO $pdo,
    string $calendarEventEntityId
): array {

    $stmt = $pdo->prepare("
        SELECT
            pp.id AS prose_projection_id,
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

    $stmt->execute([
        ':entity_id' => $calendarEventEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException(
            'No prose attached to calendar event: ' . $calendarEventEntityId
        );
    }

    $row['prose_body'] = trim((string)($row['prose_body'] ?? ''));

    if ($row['prose_body'] === '') {
        throw new RuntimeException('Attached prose draft has empty prose_body');
    }

    return $row;
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

    $calendarEventEntityId = trim((string)(
        $payload['calendar_event_entity_id']
        ?? $context['calendar_event']['entity_id']
        ?? ''
    ));

    if ($calendarEventEntityId === '') {
        throw new RuntimeException(
            'Missing calendar_event_entity_id for character tag approval workflow'
        );
    }

    $eventId = fw_resolve_calendar_event_persistence_id(
        $pdo,
        $calendarEventEntityId
    );

    $proseRow = fw_fetch_calendar_event_attached_prose_for_character_approval(
        $pdo,
        $calendarEventEntityId
    );

    $suggestions = suggest_prose_characters(
        $pdo,
        (string)$proseRow['prose_body'],
        [
            'calendar_event_entity_id' => $calendarEventEntityId,
            'calendar_event_id' => $eventId,
            'event_id' => $eventId,
            'prose_projection_id' => (int)$proseRow['prose_projection_id'],
            'prose_entity_id' => (string)$proseRow['prose_entity_id'],
        ]
    );

    $resolvedSuggestions = [];

    foreach (($suggestions['suggestions']['characters'] ?? []) as $suggestion) {
        if (!is_array($suggestion)) {
            continue;
        }

        if (($suggestion['resolution_status'] ?? null) !== 'resolved') {
            continue;
        }

        $characterEntityId = trim((string)($suggestion['resolved_entity_id'] ?? ''));

        if ($characterEntityId === '') {
            continue;
        }

        fw_validate_character_entity($pdo, $characterEntityId);

        $resolvedSuggestions[] = $suggestion;
    }

    return [
        'success' => $resolvedSuggestions !== [],
        'status' => $resolvedSuggestions === [] ? 'no_resolved_suggestions' : 'ok',
        'workflow' => 'calendar_event_approve_character_tags',
        'tier' => 3,
        'entity_id' => $calendarEventEntityId,
        'context' => array_merge(
            $context,
            [
                'character_tag_approval' => [
                    'calendar_event_entity_id' => $calendarEventEntityId,
                    'calendar_event_id' => $eventId,
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

    $approvalInput = trim((string)(
        $input['character_tag_approval'] ?? ''
    ));

    $approval = fw_workflow_character_tag_approval_status(
        $approvalInput
    );

    if ($approval === 'rejected' || $approval === 'unrecognised') {
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
    $eventId = (int)($approvalContext['calendar_event_id'] ?? 0);
    $resolvedSuggestions = $approvalContext['resolved_suggestions'] ?? [];

    if ($calendarEventEntityId === '' || $eventId < 1) {
        throw new RuntimeException('Missing approved calendar event identity');
    }

    $validatedEventId = fw_resolve_calendar_event_persistence_id(
        $pdo,
        $calendarEventEntityId
    );

    if ($validatedEventId !== $eventId) {
        throw new RuntimeException(
            'Calendar event persistence id changed during character tag approval'
        );
    }

    if (!is_array($resolvedSuggestions) || $resolvedSuggestions === []) {
        throw new RuntimeException('No resolved character suggestions available to approve');
    }

    $approvedSuggestions = fw_filter_approved_character_suggestions(
        $resolvedSuggestions,
        $approvalInput
    );

    if ($approvedSuggestions === []) {
        return [
            'success' => false,
            'status' => 'no_tags_applied',
            'workflow' => 'calendar_event_approve_character_tags',
            'transition_reason' => 'empty_approved_subset',
            'context' => $context,
        ];
    }

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO calendar_event_participants (
            event_id,
            entity_id
        ) VALUES (
            :event_id,
            :entity_id
        )
    ");

    $approvedTags = [];

    foreach ($approvedSuggestions as $suggestion) {
        if (!is_array($suggestion)) {
            continue;
        }

        $characterEntityId = trim((string)($suggestion['resolved_entity_id'] ?? ''));

        if ($characterEntityId === '') {
            continue;
        }

        fw_validate_character_entity($pdo, $characterEntityId);

        $insertStmt->execute([
            ':event_id' => $eventId,
            ':entity_id' => $characterEntityId,
        ]);

        $approvedTags[] = [
            'event_id' => $eventId,
            'calendar_event_entity_id' => $calendarEventEntityId,
            'entity_id' => $characterEntityId,
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
