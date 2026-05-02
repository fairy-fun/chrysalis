<?php

declare(strict_types=1);

require_once __DIR__ . '/../entities/entity_creator.php';
require_once __DIR__ . '/../prose/prose_draft_creator.php';
require_once __DIR__ . '/../prose/prose_projection_writer.php';

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

    if (!is_int($sequenceIndex) || $sequenceIndex < 1) {
        throw new InvalidArgumentException('sequence_index is required and must be a positive integer');
    }

    if ($recurrenceGroupId !== null && (!is_int($recurrenceGroupId) || $recurrenceGroupId < 1)) {
        throw new InvalidArgumentException('recurrence_group_id must be null or a positive integer');
    }

    $annotations = prose_normalise_annotations($body);

    if (!prose_entity_exists_for_type($pdo, $dreamerEntityId, 'entity_type_character')) {
        throw new InvalidArgumentException('Invalid dreamer_entity_id: ' . $dreamerEntityId);
    }

    $expectedJournalEntityId = 'dream_journal:' . $dreamerEntityId;

    if ($journalEntityId !== $expectedJournalEntityId) {
        throw new InvalidArgumentException(
            'journal_entity_id must match dreamer_entity_id. Expected: ' . $expectedJournalEntityId
        );
    }

    if (!prose_entity_exists_for_type($pdo, $journalEntityId, 'dream_journal')) {
        throw new InvalidArgumentException('Invalid journal_entity_id: ' . $journalEntityId);
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.projection_type_classvals
        WHERE classval_id = :id
    ");

    $stmt->execute([':id' => 'projection_type_dream_journal']);

    if ((int)$stmt->fetchColumn() !== 1) {
        throw new InvalidArgumentException('Invalid projection_type_id: projection_type_dream_journal');
    }

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        /**
         * 🔑 Deterministic identity (CRITICAL for retry safety)
         */
        $identitySeed = hash('sha256', $dreamerEntityId . ':' . $sequenceIndex);

        $dreamEntityId = 'dream:' . substr($identitySeed, 0, 32);
        $proseEntityId = 'prose_draft:' . substr($identitySeed, 0, 32);

        create_entity($pdo, $proseEntityId, 'entity_type_prose_draft');
        create_entity($pdo, $dreamEntityId, 'dream');

        $validatedAnnotations = prose_validate_annotations($pdo, $annotations, $proseBody);

        /**
         * ⚠️ Assumes entity_id is UNIQUE in prose_drafts
         * If not, this should also be upgraded to ON DUPLICATE KEY
         */
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
            ON DUPLICATE KEY UPDATE
                entity_id = entity_id
        ");

        $insertDraft->execute([
            ':entity_id' => $proseEntityId,
            ':title' => $title,
            ':prose_body' => $proseBody,
            ':author_entity_id' => $dreamerEntityId,
        ]);

        /**
         * Recover prose_draft id (works for both insert + duplicate)
         */
        $stmt = $pdo->prepare("
            SELECT id
            FROM sxnzlfun_chrysalis.prose_drafts
            WHERE entity_id = :entity_id
            LIMIT 1
        ");

        $stmt->execute([':entity_id' => $proseEntityId]);
        $proseDraftId = (int)$stmt->fetchColumn();

        if ($proseDraftId < 1) {
            throw new RuntimeException('Failed to resolve prose draft');
        }

        /**
         * ✅ Idempotent dream insert
         */
        $insertDream = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.dreams (
                dream_entity_id,
                prose_entity_id,
                dreamer_entity_id,
                dreamed_at,
                sequence_index,
                recurrence_group_id,
                created_at,
                updated_at
            ) VALUES (
                :dream_entity_id,
                :prose_entity_id,
                :dreamer_entity_id,
                :dreamed_at,
                :sequence_index,
                :recurrence_group_id,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                dream_entity_id = dream_entity_id
        ");

        $insertDream->execute([
            ':dream_entity_id' => $dreamEntityId,
            ':prose_entity_id' => $proseEntityId,
            ':dreamer_entity_id' => $dreamerEntityId,
            ':dreamed_at' => $dreamedAt,
            ':sequence_index' => $sequenceIndex,
            ':recurrence_group_id' => $recurrenceGroupId,
        ]);

        /**
         * Recover dream row (insert OR duplicate)
         */
        $stmt = $pdo->prepare("
            SELECT id, dream_entity_id, prose_entity_id
            FROM sxnzlfun_chrysalis.dreams
            WHERE dreamer_entity_id = :dreamer
              AND sequence_index = :sequence
            LIMIT 1
        ");

        $stmt->execute([
            ':dreamer' => $dreamerEntityId,
            ':sequence' => $sequenceIndex,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('Failed to resolve dream row');
        }

        /**
         * 🔒 Invariant enforcement (no identity drift)
         */
        if ($row['dream_entity_id'] !== $dreamEntityId) {
            throw new RuntimeException(
                "Dream identity mismatch at sequence {$sequenceIndex} for {$dreamerEntityId}"
            );
        }

        $dreamId = (int)$row['id'];

        $projectionId = insert_prose_projection(
            $pdo,
            $proseDraftId,
            'projection_type_dream_journal',
            $journalEntityId,
            'primary',
            $sequenceIndex,
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