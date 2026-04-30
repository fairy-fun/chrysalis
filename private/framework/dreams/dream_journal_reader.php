<?php


declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_draft_creator.php';

function get_dream_journal_entries(PDO $pdo, string $journalEntityId): array
{
    $journalEntityId = trim($journalEntityId);

    if ($journalEntityId === '') {
        throw new InvalidArgumentException('journal_entity_id must be non-empty');
    }

    if (!prose_entity_exists_for_type($pdo, $journalEntityId, 'dream_journal')) {
        throw new InvalidArgumentException('Invalid journal_entity_id: ' . $journalEntityId);
    }

    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.dream_entity_id,
            d.prose_entity_id,
            d.dreamer_entity_id,
            d.dreamed_at,
            d.sequence_index,
            d.recurrence_group_id,

            pd.title,
            pd.prose_body

        FROM sxnzlfun_chrysalis.prose_projections pp
        INNER JOIN sxnzlfun_chrysalis.prose_drafts pd
            ON pd.id = pp.prose_draft_id

        INNER JOIN sxnzlfun_chrysalis.dreams d
            ON d.prose_entity_id = pd.entity_id

        WHERE pp.projection_type_id = 'dream_journal'
          AND pp.target_entity_id = :journal_entity_id

        ORDER BY
            CASE
                WHEN d.dreamed_at IS NOT NULL THEN 0
                ELSE 1
            END ASC,
            d.dreamed_at ASC,
            d.sequence_index IS NULL ASC,
            d.sequence_index ASC,
            d.id ASC
    ");

    $stmt->execute([
        ':journal_entity_id' => $journalEntityId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $rows ?: [];
}