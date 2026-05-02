<?php

declare(strict_types=1);
require_once __DIR__ . '/../prose/prose_draft_creator.php';
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

    // 🔍 Extract character_id
    $characterId = substr($targetEntityId, strlen('dream_journal:'));

    if ($characterId === '') {
        throw new InvalidArgumentException(
            'dream_journal target must include character_id suffix'
        );
    }

    // 🔒 Enforce character existence
    if (!prose_entity_exists_for_type($pdo, $characterId, 'entity_type_character')) {
        throw new InvalidArgumentException(
            'Invalid dream_journal target: character does not exist → ' . $characterId
        );
    }

    // 🔒 Enforce canonical identity mapping
    $expectedId = 'dream_journal:' . $characterId;

    if ($targetEntityId !== $expectedId) {
        throw new InvalidArgumentException(
            'Non-canonical dream_journal id: expected ' . $expectedId . ', got ' . $targetEntityId
        );
    }

    // 🔒 Ensure entity exists with correct type
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :id
          AND entity_type_id = 'dream_journal'
    ");

    $stmt->execute([
        ':id' => $targetEntityId
    ]);

    if ((int)$stmt->fetchColumn() !== 1) {
        throw new InvalidArgumentException(
            'Dream journal entity does not exist: ' . $targetEntityId
        );
    }
}