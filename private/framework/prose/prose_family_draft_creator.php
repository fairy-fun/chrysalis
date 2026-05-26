<?php
declare(strict_types=1);

require_once __DIR__ . '/prose_draft_creator.php';
require_once __DIR__ . '/prose_metadata_deriver.php';

function create_prose_family_draft(PDO $pdo, array $body): array
{
    $entityId = prose_required_string($body, 'entity_id');
    $proseFamilyId = prose_required_positive_int($body, 'prose_family_id');
    $title = prose_optional_string_or_null($body, 'title');
    $summary = prose_optional_string_or_null($body, 'summary');
    $proseBody = prose_required_string($body, 'prose_body');
    $draftStatusId = prose_required_string($body, 'draft_status_id');
    $authorEntityId = prose_optional_string_or_null($body, 'author_entity_id');

    $metadata = derive_prose_metadata($proseBody, $body);
    $title = $title ?? $metadata['title'] ?? 'Untitled prose draft';
    $summary = $summary ?? $metadata['summary'];

    if (prose_draft_entity_exists($pdo, $entityId)) {
        throw new RuntimeException('Duplicate prose draft entity_id: ' . $entityId);
    }

    if (!prose_entity_exists_for_type($pdo, $draftStatusId, 'entity_type_status')) {
        throw new InvalidArgumentException('Invalid draft_status_id: ' . $draftStatusId);
    }

    if ($authorEntityId !== null && !prose_entity_exists($pdo, $authorEntityId)) {
        throw new InvalidArgumentException('Invalid author_entity_id: ' . $authorEntityId);
    }

    $familyStmt = $pdo->prepare('
        SELECT
            id,
            entity_id
        FROM sxnzlfun_chrysalis.prose_families
        WHERE id = :id
        LIMIT 1
    ');

    $familyStmt->execute([
        ':id' => $proseFamilyId,
    ]);

    $family = $familyStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($family)) {
        throw new InvalidArgumentException('Invalid prose_family_id: ' . $proseFamilyId);
    }

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $proseDraftId = insert_prose_family_draft_row(
            $pdo,
            $proseFamilyId,
            $entityId,
            $title,
            $summary,
            $proseBody,
            $draftStatusId,
            $authorEntityId
        );

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    return [
        'prose_family' => [
            'id' => $proseFamilyId,
            'entity_id' => $family['entity_id'],
        ],
        'prose' => [
            'id' => $proseDraftId,
            'entity_id' => $entityId,
            'title' => $title,
        ],
        'projection' => null,
        'publication_changed' => false,
        'export_authority_changed' => false,
    ];
}

function create_calendar_event_prose_family_draft(PDO $pdo, array $body): array
{
    $entityId = prose_required_string($body, 'entity_id');
    $calendarEventEntityId = prose_required_string($body, 'calendar_event_entity_id');
    $title = prose_optional_string_or_null($body, 'title');
    $summary = prose_optional_string_or_null($body, 'summary');
    $proseBody = prose_required_string($body, 'prose_body');
    $draftStatusId = prose_required_string($body, 'draft_status_id');
    $authorEntityId = prose_optional_string_or_null($body, 'author_entity_id');

    $projectionClassvalId = prose_optional_string_or_null($body, 'projection_classval_id')
        ?? 'projection_type_timeline_view';
    $projectionTypeId = prose_optional_string_or_null($body, 'projection_type_id')
        ?? 'projection_type_timeline_view';
    $roleId = prose_optional_string_or_null($body, 'role_id')
        ?? 'prose_projection_role_primary';

    $projectionOrder = $body['projection_order'] ?? 1;

    if (is_string($projectionOrder) && preg_match('/^[0-9]+$/', $projectionOrder) === 1) {
        $projectionOrder = (int) $projectionOrder;
    }

    if (!is_int($projectionOrder) || $projectionOrder < 1) {
        throw new InvalidArgumentException('projection_order must be a positive integer');
    }

    if (!str_starts_with($calendarEventEntityId, 'calendar_event:')) {
        throw new InvalidArgumentException(
            'calendar_event_entity_id must be a canonical calendar event entity_id'
        );
    }

    $metadata = derive_prose_metadata($proseBody, $body);
    $title = $title ?? $metadata['title'] ?? 'Untitled prose draft';
    $summary = $summary ?? $metadata['summary'];

    if (prose_draft_entity_exists($pdo, $entityId)) {
        throw new RuntimeException('Duplicate prose draft entity_id: ' . $entityId);
    }

    if (!prose_entity_exists_for_type($pdo, $draftStatusId, 'entity_type_status')) {
        throw new InvalidArgumentException('Invalid draft_status_id: ' . $draftStatusId);
    }

    if ($authorEntityId !== null && !prose_entity_exists($pdo, $authorEntityId)) {
        throw new InvalidArgumentException('Invalid author_entity_id: ' . $authorEntityId);
    }

    if (!prose_classval_exists($pdo, $projectionClassvalId)) {
        throw new InvalidArgumentException(
            'Invalid projection_classval_id: ' . $projectionClassvalId
        );
    }

    if (!prose_calendar_projection_type_exists($pdo, $projectionTypeId)) {
        throw new InvalidArgumentException(
            'Invalid projection_type_id: ' . $projectionTypeId
        );
    }

    $existingProjection = resolve_calendar_event_primary_prose_projection(
        $pdo,
        $calendarEventEntityId,
        $projectionTypeId,
        $roleId
    );

    if ($existingProjection !== null) {
        $result = create_prose_family_draft(
            $pdo,
            [
                'entity_id' => $entityId,
                'prose_family_id' => (int) $existingProjection['prose_family_id'],
                'title' => $title,
                'summary' => $summary,
                'prose_body' => $proseBody,
                'draft_status_id' => $draftStatusId,
                'author_entity_id' => $authorEntityId,
            ]
        );

        $result['projection'] = [
            'id' => (int) $existingProjection['id'],
            'target_entity_id' => $calendarEventEntityId,
            'projection_type_id' => $projectionTypeId,
            'role_id' => $roleId,
            'published_prose_draft_id' => $existingProjection['published_prose_draft_id'] !== null
                ? (int) $existingProjection['published_prose_draft_id']
                : null,
            'is_export_target' => (int) ($existingProjection['is_export_target'] ?? 0),
        ];
        $result['calendar_event_entity_id'] = $calendarEventEntityId;
        $result['prose_family_id'] = (int) $existingProjection['prose_family_id'];

        $result['projection_created'] = false;
        $result['publication_changed'] = false;
        $result['export_authority_changed'] = false;

        return $result;
    }

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $proseFamilyEntityId = 'prose_family:' . bin2hex(random_bytes(16));
        $proseFamilyId = create_prose_family($pdo, $proseFamilyEntityId);

        $proseDraftId = insert_prose_family_draft_row(
            $pdo,
            $proseFamilyId,
            $entityId,
            $title,
            $summary,
            $proseBody,
            $draftStatusId,
            $authorEntityId
        );

        $projectionId = insert_prose_projection(
            $pdo,
            $proseFamilyId,
            $proseDraftId,
            $projectionClassvalId,
            $projectionTypeId,
            $calendarEventEntityId,
            $roleId,
            $projectionOrder,
        );

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    return [
        'calendar_event_entity_id' => $calendarEventEntityId,
        'prose_family_id' => $proseFamilyId,

        'prose_family' => [
            'id' => $proseFamilyId,
            'entity_id' => $proseFamilyEntityId,
        ],

        'prose' => [
            'id' => $proseDraftId,
            'entity_id' => $entityId,
            'title' => $title,
        ],

        'projection' => [
            'id' => $projectionId,
            'target_entity_id' => $calendarEventEntityId,
            'projection_type_id' => $projectionTypeId,
            'role_id' => $roleId,
            'published_prose_draft_id' => $proseDraftId,
            'is_export_target' => 0,
        ],

        'projection_created' => true,
        'publication_changed' => false,
        'export_authority_changed' => false,
    ];
}

function resolve_calendar_event_primary_prose_projection(
    PDO $pdo,
    string $targetEntityId,
    string $projectionTypeId = 'projection_type_timeline_view',
    string $roleId = 'prose_projection_role_primary'
): ?array {

    $stmt = $pdo->prepare('
        SELECT
            pp.id,
            pp.prose_family_id,
            pf.entity_id AS prose_family_entity_id,
            pp.published_prose_draft_id,
            pp.is_export_target,
            pp.projection_order,
            pp.role_id,
            pp.projection_type_id
        FROM sxnzlfun_chrysalis.prose_projections pp
        INNER JOIN sxnzlfun_chrysalis.prose_families pf
            ON pf.id = pp.prose_family_id
        WHERE pp.target_entity_id = :target_entity_id
          AND pp.role_id = :role_id
          AND pp.projection_type_id = :projection_type_id
        ORDER BY
            pp.is_export_target DESC,
            pp.projection_order ASC,
            pp.id ASC
        LIMIT 1
    ');

    $stmt->execute([
        ':target_entity_id' => $targetEntityId,
        ':role_id' => $roleId,
        ':projection_type_id' => $projectionTypeId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function insert_prose_family_draft_row(
    PDO $pdo,
    int $proseFamilyId,
    string $entityId,
    string $title,
    ?string $summary,
    string $proseBody,
    string $draftStatusId,
    ?string $authorEntityId
): int {

    $insertDraft = $pdo->prepare('
        INSERT INTO sxnzlfun_chrysalis.prose_drafts (
            prose_family_id,
            entity_id,
            title,
            summary,
            prose_body,
            draft_status_id,
            author_entity_id
        ) VALUES (
            :prose_family_id,
            :entity_id,
            :title,
            :summary,
            :prose_body,
            :draft_status_id,
            :author_entity_id
        )
    ');

    $insertDraft->execute([
        ':prose_family_id' => $proseFamilyId,
        ':entity_id' => $entityId,
        ':title' => $title,
        ':summary' => $summary,
        ':prose_body' => $proseBody,
        ':draft_status_id' => $draftStatusId,
        ':author_entity_id' => $authorEntityId,
    ]);

    $proseDraftId = (int) $pdo->lastInsertId();

    if ($proseDraftId < 1) {
        throw new RuntimeException('Failed to create prose draft');
    }

    return $proseDraftId;
}
