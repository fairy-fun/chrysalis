<?php

declare(strict_types=1);

/**
 * Export selection is projection-authoritative.
 *
 * Canonicality is projection-contextual:
 * prose drafts are revisions,
 * projections select published drafts locally.
 *
 * Never infer publication state from draft chronology.
 */

function resolve_prose_export_text(
    PDO $pdo,
    string $exportTargetKey
): array {

    $stmt = $pdo->prepare("
        SELECT
            pp.id AS projection_id,
            pp.prose_family_id,
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pp.target_entity_id,
            pp.projection_order,
            pp.export_target_key,
            pd.prose_body
        FROM prose_projections pp
        JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
        WHERE pp.export_target_key = :export_target_key
          AND pp.is_export_target = 1
        ORDER BY
            pp.target_entity_id,
            pp.projection_order,
            pp.id
    ");

    $stmt->execute([
        ':export_target_key' => $exportTargetKey,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}