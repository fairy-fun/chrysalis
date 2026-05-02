<?php

declare(strict_types=1);

/**
 * Ensures the given target_entity_id refers to a valid dream journal entity.
 *
 * Expected format:
 *   dream_journal:<entity_id>
 *
 * Enforces:
 *   - entity exists in sxnzlfun_chrysalis.entities
 *   - entity_type_id = 'dream_journal'
 */
function require_dream_journal_projection_target_entity(PDO $pdo, string $targetEntityId): void
{
    $targetEntityId = trim($targetEntityId);

    if (!str_starts_with($targetEntityId, 'dream_journal:')) {
        throw new InvalidArgumentException(
            'Expected dream_journal:* target_entity_id, got: ' . $targetEntityId
        );
    }

    $entityId = substr($targetEntityId, strlen('dream_journal:'));

    if ($entityId === '') {
        throw new InvalidArgumentException(
            'dream_journal target_entity_id must include an entity id'
        );
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :id
          AND entity_type_id = 'dream_journal'
    ");

    $stmt->execute([
        ':id' => $entityId
    ]);

    if ((int)$stmt->fetchColumn() !== 1) {
        throw new InvalidArgumentException(
            'Invalid dream_journal target_entity_id: ' . $targetEntityId
        );
    }
}