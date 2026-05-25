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
