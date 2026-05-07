<?php

declare(strict_types=1);

function resolve_prose_export_text(
    PDO $pdo,
    string $projectionTypeId
): array {
    $stmt = $pdo->prepare("
        SELECT
            pp.id AS projection_id,
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pp.target_entity_id,
            pp.projection_order,
            pd.prose_body
        FROM prose_projections pp
        JOIN prose_drafts pd
            ON pd.id = pp.prose_draft_id
        WHERE pp.projection_type_id = :projection_type_id
          AND pp.is_export_target = 1
        ORDER BY
            pp.target_entity_id,
            pp.projection_order,
            pp.id
    ");

    $stmt->execute([
        ':projection_type_id' => $projectionTypeId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}