<?php

function suggestNextCharacterBeat(
    PDO $pdo,
    string $characterEntityId,
    ?string $projectionEntityId,
    string $currentThemeEntityId
): array {
    $sql = "
        SELECT
            candidate.theme_entity_id,
            tal.beat_label,
            SUM(candidate.weighted_score) AS score,
            SUM(candidate.subject_hits) AS subject_hits,
            SUM(candidate.global_hits) AS global_hits
        FROM (
            SELECT
                nto2.theme_entity_id,
                COUNT(*) * 1.00 AS weighted_score,
                COUNT(*) AS subject_hits,
                0 AS global_hits
            FROM sxnzlfun_chrysalis.narrative_theme_observations nto1
            JOIN sxnzlfun_chrysalis.narrative_theme_observations nto2
                ON nto2.subject_entity_id = nto1.subject_entity_id
               AND nto2.projection_entity_id <=> nto1.projection_entity_id
               AND nto2.sequence_index > nto1.sequence_index
               AND nto2.sequence_index <= nto1.sequence_index + 3
            WHERE nto1.subject_entity_id = :subject_entity_id_subject
              AND nto1.theme_entity_id = :current_theme_entity_id_subject
              AND (
                    :projection_entity_id_subject IS NULL
                    OR nto1.projection_entity_id = :projection_entity_id_subject_value
                  )
            GROUP BY nto2.theme_entity_id

            UNION ALL

            SELECT
                nto2.theme_entity_id,
                COUNT(*) * 0.25 AS weighted_score,
                0 AS subject_hits,
                COUNT(*) AS global_hits
            FROM sxnzlfun_chrysalis.narrative_theme_observations nto1
            JOIN sxnzlfun_chrysalis.narrative_theme_observations nto2
                ON nto2.sequence_index > nto1.sequence_index
               AND nto2.sequence_index <= nto1.sequence_index + 3
            WHERE nto1.theme_entity_id = :current_theme_entity_id_global
              AND nto1.subject_entity_id <> :subject_entity_id_global
              AND (
                    :projection_entity_id_global IS NULL
                    OR nto1.projection_entity_id = :projection_entity_id_global_value
                  )
            GROUP BY nto2.theme_entity_id
        ) candidate
        LEFT JOIN sxnzlfun_chrysalis.theme_author_labels tal
            ON tal.theme_entity_id = candidate.theme_entity_id
        GROUP BY
            candidate.theme_entity_id,
            tal.beat_label
        ORDER BY
            score DESC,
            subject_hits DESC,
            global_hits DESC
        LIMIT 5
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':subject_entity_id_subject', $characterEntityId);
    $stmt->bindValue(':subject_entity_id_global', $characterEntityId);

    $stmt->bindValue(':current_theme_entity_id_subject', $currentThemeEntityId);
    $stmt->bindValue(':current_theme_entity_id_global', $currentThemeEntityId);

    if ($projectionEntityId === null) {
        $stmt->bindValue(':projection_entity_id_subject', null, PDO::PARAM_NULL);
        $stmt->bindValue(':projection_entity_id_subject_value', null, PDO::PARAM_NULL);
        $stmt->bindValue(':projection_entity_id_global', null, PDO::PARAM_NULL);
        $stmt->bindValue(':projection_entity_id_global_value', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':projection_entity_id_subject', $projectionEntityId);
        $stmt->bindValue(':projection_entity_id_subject_value', $projectionEntityId);
        $stmt->bindValue(':projection_entity_id_global', $projectionEntityId);
        $stmt->bindValue(':projection_entity_id_global_value', $projectionEntityId);
    }

    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'status' => 'ok',
        'subject_entity_id' => $characterEntityId,
        'projection_entity_id' => $projectionEntityId,
        'current_theme_entity_id' => $currentThemeEntityId,
        'suggestion_count' => count($rows),
        'suggestions' => $rows,
    ];
}