<?php

declare(strict_types=1);

/**
 * Export assembly is context-authoritative.
 *
 * Projections remain publication-authoritative:
 * each projection selects the published prose draft
 * for a prose family within a projection topology.
 *
 * Export contexts assemble projections into
 * first-class export structures.
 */

function resolve_prose_export_text(
    PDO $pdo,
    string $exportContextKey
): array {

    $stmt = $pdo->prepare("
        SELECT
            pec.id AS export_context_id,
            pec.export_context_key,
            pec.label AS export_context_label,

            pecp.id AS export_context_projection_id,
            pecp.export_order,

            pp.id AS projection_id,
            pp.prose_family_id,
            pp.projection_classval_id,
            pp.projection_type_id,
            pp.target_entity_id,
            pp.role_id,
            pp.projection_order,

            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pd.summary,
            pd.prose_body

        FROM prose_export_contexts pec

        JOIN prose_export_context_projections pecp
            ON pecp.export_context_id = pec.id

        JOIN prose_projections pp
            ON pp.id = pecp.prose_projection_id

        JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id

        WHERE pec.export_context_key = :export_context_key

        ORDER BY
            pecp.export_order,
            pp.projection_order,
            pp.id
    ");

    $stmt->execute([
        ':export_context_key' => $exportContextKey,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}