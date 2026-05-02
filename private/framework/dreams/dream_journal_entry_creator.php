<?php

declare(strict_types=1);

require_once __DIR__ . '/../entities/entity_creator.php';
require_once __DIR__ . '/../prose/prose_draft_creator.php';
require_once __DIR__ . '/../prose/prose_projection_writer.php';

function next_dream_id(PDO $pdo): int
{
    $stmt = $pdo->query("
        SELECT COALESCE(MAX(id), 0) + 1
        FROM sxnzlfun_chrysalis.dreams
        FOR UPDATE
    ");

    $id = (int)$stmt->fetchColumn();

    if ($id < 1) {
        throw new RuntimeException('Failed to allocate dream id');
    }

    return $id;
}

function create_dream_journal_entry(PDO $pdo, array $body): array
{
    $dreamerEntityId = prose_required_string($body, 'dreamer_entity_id');
    $journalEntityId = prose_required_string($body, 'journal_entity_id');
    $title = prose_required_string($body, 'title');
    $proseBody = prose_required_string($body, 'prose_body');

    $dreamedAt = $body['dreamed_at'] ?? null;
    $sequenceIndex = $body['sequence_index'] ?? null;
    $recurrenceGroupId = $body['recurrence_group_id'] ?? null;

    if ($dreamedAt !== null && (!is_string($dreamedAt) || trim($dreamedAt) === '')) {
        throw new InvalidArgumentException('dreamed_at must be null or a valid datetime string');
    }

    if ($sequenceIndex !== null && (!is_int($sequenceIndex) || $sequenceIndex < 1)) {
        throw new InvalidArgumentException('sequence_index must be null or a positive integer');
    }

    if ($recurrenceGroupId !== null && (!is_int($recurrenceGroupId) || $recurrenceGroupId < 1)) {
        throw new InvalidArgumentException('recurrence_group_id must be null or a positive integer');
    }

    $annotations = prose_normalise_annotations($body);

    if (!prose_entity_exists($pdo, $dreamerEntityId)) {
        throw new InvalidArgumentException('Invalid dreamer_entity_id: ' . $dreamerEntityId);
    }

    if (!prose_entity_exists_for_type($pdo, $journalEntityId, 'dream_journal')) {
        throw new InvalidArgumentException('Invalid journal_entity_id: ' . $journalEntityId);
    }

    if (!prose_classval_exists($pdo, 'projection_type_dream_journal')) {
        throw new InvalidArgumentException('Invalid projection_type_id: projection_type_dream_journal');
    }

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $dreamId = next_dream_id($pdo);

        $dreamEntityId = 'dream:' . $dreamId;
        $proseEntityId = 'prose_draft:' . $dreamId;

        create_entity($pdo, $proseEntityId, 'entity_type_prose_draft');
        create_entity($pdo, $dreamEntityId, 'dream');

        $validatedAnnotations = prose_validate_annotations($pdo, $annotations, $proseBody);

        $insertDraft = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.prose_drafts (
                entity_id,
                title,
                prose_body,
                draft_status_id,
                author_entity_id
            ) VALUES (
                :entity_id,
                :title,
                :prose_body,
                'prose_status_draft',
                :author_entity_id
            )
        ");

        $insertDraft->execute([
            ':entity_id' => $proseEntityId,
            ':title' => $title,
            ':prose_body' => $proseBody,
            ':author_entity_id' => $dreamerEntityId,
        ]);

        $proseDraftId = (int)$pdo->lastInsertId();

        if ($proseDraftId < 1) {
            throw new RuntimeException('Failed to create prose draft');
        }

        $insertDream = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.dreams (
                id,
                dream_entity_id,
                prose_entity_id,
                dreamer_entity_id,
                dreamed_at,
                sequence_index,
                recurrence_group_id,
                created_at,
                updated_at
            ) VALUES (
                :id,
                :dream_entity_id,
                :prose_entity_id,
                :dreamer_entity_id,
                :dreamed_at,
                :sequence_index,
                :recurrence_group_id,
                NOW(),
                NOW()
            )
        ");

        $insertDream->execute([
            ':id' => $dreamId,
            ':dream_entity_id' => $dreamEntityId,
            ':prose_entity_id' => $proseEntityId,
            ':dreamer_entity_id' => $dreamerEntityId,
            ':dreamed_at' => $dreamedAt,
            ':sequence_index' => $sequenceIndex,
            ':recurrence_group_id' => $recurrenceGroupId,
        ]);

        $projectionId = insert_prose_projection(
            $pdo,
            $proseDraftId,
            'projection_type_dream_journal',
            $journalEntityId,
            'primary',
            $sequenceIndex ?? $dreamId,
            1
        );

        $insertedAnnotations = insert_prose_annotations(
            $pdo,
            $proseEntityId,
            $validatedAnnotations
        );

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'dream' => [
                'id' => $dreamId,
                'entity_id' => $dreamEntityId,
                'dreamer_entity_id' => $dreamerEntityId,
                'dreamed_at' => $dreamedAt,
                'sequence_index' => $sequenceIndex,
                'recurrence_group_id' => $recurrenceGroupId,
            ],
            'prose' => [
                'entity_id' => $proseEntityId,
                'title' => $title,
            ],
            'projection' => [
                'id' => $projectionId,
                'projection_type_id' => 'projection_type_dream_journal',
                'target_entity_id' => $journalEntityId,
                'role_id' => 'primary',
            ],
            'annotations' => [
                'inserted' => $insertedAnnotations,
            ],
        ];

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}