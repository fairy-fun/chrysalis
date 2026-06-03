<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_character_suggester.php';
require_once __DIR__ . '/../prose/prose_character_role_suggester.php';
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

function fw_workflow_character_role_approval_status(
    mixed $approval
): string {

    $value = mb_strtolower(trim((string)$approval));

    if ($value === '') {
        return 'empty';
    }

    if (
        in_array($value, ['yes', 'y', 'approve', 'approved', 'apply'], true)
        || str_contains($value, 'approve all role')
        || str_contains($value, 'apply all role')
    ) {
        return 'approved';
    }

    if (
        in_array($value, ['no', 'n', 'reject', 'rejected', 'cancel'], true)
        || str_contains($value, 'apply identities only')
        || str_contains($value, 'identity only')
        || str_contains($value, 'reject all role')
        || str_contains($value, 'no role')
        || str_contains($value, 'skip role')
    ) {
        return 'identities_only';
    }

    if (
        preg_match('/char-[a-z0-9\-]+/i', $value) === 1
        || preg_match('/(?:dream_)?role_[a-z0-9_]+/i', $value) === 1
    ) {
        return 'partial';
    }

    return 'unrecognised';
}

function fw_fetch_calendar_relation_roles_by_key(PDO $pdo): array
{
    return fetch_calendar_relation_role_vocabulary($pdo);
}

function fw_match_approved_role_record(
    array $rolesByKey,
    ?string $identifier
): ?array {

    $normalized = mb_strtolower(trim((string)$identifier));

    if ($normalized === '') {
        return null;
    }

    $role = $rolesByKey[$normalized] ?? null;

    return is_array($role) ? $role : null;
}

function fw_parse_role_assignments_from_text(
    string $approvalInput,
    array $rolesByKey
): array {

    $assignments = [];

    preg_match_all(
        '/(CHAR-[A-Z0-9\-]+)[^.;,\n]*?\b(?:as|=|role)\s+((?:dream_)?role_[a-z0-9_]+)/i',
        $approvalInput,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as $match) {
        $entityId = strtoupper(trim((string)($match[1] ?? '')));
        $roleIdentifier = trim((string)($match[2] ?? ''));

        if ($entityId === '' || $roleIdentifier === '') {
            continue;
        }

        $role = fw_match_approved_role_record($rolesByKey, $roleIdentifier);

        if (!is_array($role)) {
            continue;
        }

        $assignments[$entityId] = [
            'approved_role_id' => $role['id'],
            'approved_role_code' => $role['code'],
            'approved_role_label' => $role['label'],
            'decision' => 'approved',
        ];
    }

    return $assignments;
}

function fw_filter_approved_character_roles(
    PDO $pdo,
    array $roleSuggestions,
    string $approvalInput
): array {

    $status = fw_workflow_character_role_approval_status($approvalInput);

    if ($status === 'empty' || $status === 'identities_only' || $status === 'unrecognised') {
        return [];
    }

    $rolesByKey = fw_fetch_calendar_relation_roles_by_key($pdo);
    $assignments = fw_parse_role_assignments_from_text($approvalInput, $rolesByKey);

    $approved = [];

    foreach ($roleSuggestions as $roleSuggestion) {
        if (!is_array($roleSuggestion)) {
            continue;
        }

        $entityId = strtoupper(trim((string)($roleSuggestion['resolved_entity_id'] ?? '')));

        if ($entityId === '') {
            continue;
        }

        if (isset($assignments[$entityId])) {
            $approved[] = array_merge(
                $roleSuggestion,
                $assignments[$entityId]
            );
            continue;
        }

        if ($status !== 'approved') {
            continue;
        }

        $suggestedRoleId = trim((string)($roleSuggestion['suggested_role_id'] ?? ''));

        if ($suggestedRoleId === '') {
            continue;
        }

        $approved[] = array_merge(
            $roleSuggestion,
            [
                'approved_role_id' => $roleSuggestion['suggested_role_id'],
                'approved_role_code' => $roleSuggestion['suggested_role_code'] ?? null,
                'approved_role_label' => $roleSuggestion['suggested_role_label'] ?? null,
                'decision' => 'approved',
            ]
        );
    }

    return $approved;
}

function fw_calendar_event_participants_has_column(PDO $pdo, string $columnName): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'calendar_event_participants'
          AND COLUMN_NAME = :column_name
        LIMIT 1
    ");

    $stmt->execute([
        ':column_name' => $columnName,
    ]);

    return is_array($stmt->fetch(PDO::FETCH_ASSOC));
}

function fw_resolve_calendar_event_participants_event_column(PDO $pdo): string
{
    foreach (['calendar_event_id', 'event_id'] as $candidate) {
        if (fw_calendar_event_participants_has_column($pdo, $candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException(
        'calendar_event_participants is missing an event foreign key column'
    );
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

function fw_execute_workflow_calendar_event_prepare_character_role_approval(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $approvalInput = trim((string)($input['character_tag_approval'] ?? ''));

    if (fw_workflow_character_tag_approval_status($approvalInput) !== 'approved'
        && fw_workflow_character_tag_approval_status($approvalInput) !== 'partial') {
        return [
            'success' => false,
            'status' => 'identity_approval_not_granted',
            'workflow' => 'calendar_event_approve_character_tags',
            'transition_reason' => 'identity_approval_not_granted',
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
            'status' => 'no_approved_identities',
            'workflow' => 'calendar_event_approve_character_tags',
            'transition_reason' => 'empty_approved_identity_subset',
            'context' => $context,
        ];
    }

    $proseRow = fw_fetch_calendar_event_attached_prose_for_character_approval(
        $pdo,
        $calendarEventEntityId
    );

    $roleSuggestions = suggest_prose_character_roles(
        $pdo,
        (string)$proseRow['prose_body'],
        $approvedSuggestions,
        [
            'calendar_event_entity_id' => $calendarEventEntityId,
            'calendar_event_id' => $eventId,
            'event_id' => $eventId,
            'prose_projection_id' => (int)$proseRow['prose_projection_id'],
            'prose_entity_id' => (string)$proseRow['prose_entity_id'],
        ]
    );

    return [
        'success' => ((int)($roleSuggestions['role_suggestion_count'] ?? 0)) > 0,
        'status' => ((int)($roleSuggestions['role_suggestion_count'] ?? 0)) > 0
            ? 'ok'
            : 'no_role_suggestions',
        'workflow' => 'calendar_event_approve_character_tags',
        'tier' => 3,
        'entity_id' => $calendarEventEntityId,
        'context' => array_merge(
            $context,
            [
                'character_role_approval' => [
                    'calendar_event_entity_id' => $calendarEventEntityId,
                    'calendar_event_id' => $eventId,
                    'approved_identity_suggestions' => $approvedSuggestions,
                    'role_suggestions' => $roleSuggestions['roles'] ?? [],
                    'role_suggestion_count' => $roleSuggestions['role_suggestion_count'] ?? 0,
                    'role_vocabulary' => $roleSuggestions['role_vocabulary'] ?? [],
                    'approval_required' => true,
                ],
            ]
        ),
    ];
}

function fw_execute_workflow_calendar_event_apply_character_tags_and_roles(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $approvalInput = trim((string)($input['character_tag_approval'] ?? ''));
    $approval = fw_workflow_character_tag_approval_status($approvalInput);

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

    $eventColumn = fw_resolve_calendar_event_participants_event_column($pdo);

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO calendar_event_participants (
            {$eventColumn},
            entity_id
        ) VALUES (
            :event_id,
            :entity_id
        )
    ");

    $updateRoleStmt = $pdo->prepare("
        UPDATE calendar_event_participants
        SET role_id = :role_id
        WHERE {$eventColumn} = :event_id
          AND entity_id = :entity_id
    ");

    $roleContext = $context['character_role_approval'] ?? [];
    $roleSuggestions = is_array($roleContext)
        ? ($roleContext['role_suggestions'] ?? [])
        : [];

    $roleApprovalInput = trim((string)($input['character_role_approval'] ?? ''));
    $approvedRoles = is_array($roleSuggestions)
        ? fw_filter_approved_character_roles($pdo, $roleSuggestions, $roleApprovalInput)
        : [];

    $approvedRolesByEntity = [];

    foreach ($approvedRoles as $approvedRole) {
        if (!is_array($approvedRole)) {
            continue;
        }

        $entityId = strtoupper(trim((string)($approvedRole['resolved_entity_id'] ?? '')));

        if ($entityId === '') {
            continue;
        }

        $approvedRolesByEntity[$entityId] = $approvedRole;
    }

    $approvedTags = [];
    $appliedRoleSummaries = [];

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

        $approvedRole = $approvedRolesByEntity[strtoupper($characterEntityId)] ?? null;

        if (is_array($approvedRole)) {
            $approvedRoleId = trim((string)($approvedRole['approved_role_id'] ?? ''));

            if ($approvedRoleId !== '') {
                $updateRoleStmt->execute([
                    ':role_id' => $approvedRoleId,
                    ':event_id' => $eventId,
                    ':entity_id' => $characterEntityId,
                ]);

                $appliedRoleSummaries[] = [
                    'calendar_event_entity_id' => $calendarEventEntityId,
                    'event_id' => $eventId,
                    'entity_id' => $characterEntityId,
                    'role_id' => $approvedRoleId,
                    'role_code' => $approvedRole['approved_role_code'] ?? null,
                    'role_label' => $approvedRole['approved_role_label'] ?? null,
                ];
            }
        }

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
                'approved_character_roles' => $appliedRoleSummaries,
                'character_tag_apply_summary' => [
                    'participant_links_written' => count($approvedTags),
                    'participant_roles_written' => count($appliedRoleSummaries),
                    'participants_without_roles' => count($approvedTags) - count($appliedRoleSummaries),
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

    return fw_execute_workflow_calendar_event_apply_character_tags_and_roles(
        $pdo,
        $action,
        $input,
        $context
    );
}
