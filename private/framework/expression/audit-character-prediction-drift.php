<?php

require_once __DIR__ . '/../theme/theme_taxonomy.php';

function auditCharacterPredictionDrift(
    PDO $pdo,
    string $predictedThemeEntityId,
    string $actualThemeEntityId
): array {
    $match = classifyThemeMatch(
        $pdo,
        $predictedThemeEntityId,
        $actualThemeEntityId
    );

    return [
        'predicted_theme_entity_id' => $predictedThemeEntityId,
        'actual_theme_entity_id' => $actualThemeEntityId,
        'audit_category' => $match['match_class'],
        'score_multiplier' => $match['score_multiplier'],
    ];
}