<?php

function getThemeClusters(PDO $pdo, string $themeEntityId): array
{
    $sql = "
        SELECT
            tcm.theme_entity_id,
            tcm.cluster_id,
            tc.label,
            tc.description,
            tcm.membership_weight,
            tcm.is_primary
        FROM sxnzlfun_chrysalis.theme_cluster_membership tcm
        INNER JOIN sxnzlfun_chrysalis.theme_clusters tc
            ON tc.id = tcm.cluster_id
        WHERE tcm.theme_entity_id = :theme_entity_id
          AND tc.is_active = 1
        ORDER BY tcm.is_primary DESC, tcm.membership_weight DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['theme_entity_id' => $themeEntityId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function themesShareCluster(PDO $pdo, string $themeA, string $themeB): bool
{
    $sql = "
        SELECT 1
        FROM sxnzlfun_chrysalis.theme_cluster_membership a
        INNER JOIN sxnzlfun_chrysalis.theme_cluster_membership b
            ON b.cluster_id = a.cluster_id
        INNER JOIN sxnzlfun_chrysalis.theme_clusters tc
            ON tc.id = a.cluster_id
        WHERE a.theme_entity_id = :theme_a
          AND b.theme_entity_id = :theme_b
          AND tc.is_active = 1
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'theme_a' => $themeA,
        'theme_b' => $themeB,
    ]);

    return (bool) $stmt->fetchColumn();
}

function classifyThemeMatch(PDO $pdo, string $predictedThemeId, string $actualThemeId): array
{
    if ($predictedThemeId === $actualThemeId) {
        return [
            'match_class' => 'EXACT_MATCH',
            'score_multiplier' => 1.00,
        ];
    }

    if (themesShareCluster($pdo, $predictedThemeId, $actualThemeId)) {
        return [
            'match_class' => 'NEAR_MATCH',
            'score_multiplier' => 0.50,
        ];
    }

    return [
        'match_class' => 'DIVERGENCE',
        'score_multiplier' => 0.00,
    ];
}
