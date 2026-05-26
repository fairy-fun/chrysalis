<?php

declare(strict_types=1);

require_once __DIR__ . '/../spans/tokenize_surface.php';
require_once __DIR__ . '/../spans/normalize_surface.php';
require_once __DIR__ . '/../arbitration/candidate_deduper.php';

function prose_character_try_token_decomposition(
    PDO $pdo,
    string $surfaceForm
): array {

    $surfaceTokens = prose_character_tokenize_surface($surfaceForm);

    if (count($surfaceTokens) < 2) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, canonical_label
             FROM entities
             WHERE entity_type_id = 'entity_type_character'
               AND canonical_label IS NOT NULL
             LIMIT 500"
        );

        $stmt->execute();
    } catch (Throwable $e) {
        return [];
    }

    $candidates = [];

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {

        $canonicalLabel = trim(
            (string)($row['canonical_label'] ?? '')
        );

        $canonicalTokens = prose_character_tokenize_surface(
            $canonicalLabel
        );

        if (count($canonicalTokens) < count($surfaceTokens)) {
            continue;
        }

        $cursor = 0;

        foreach ($canonicalTokens as $canonicalToken) {
            if (($surfaceTokens[$cursor] ?? null) === $canonicalToken) {
                $cursor++;
            }
        }

        if ($cursor < count($surfaceTokens)) {
            continue;
        }

        prose_character_append_candidate($candidates, [
            'resolved_entity_id'
                => trim((string)($row['id'] ?? '')),

            'candidate_label'
                => $canonicalLabel,

            'resolution_method_classval_id'
                => 'RESOLUTION_METHOD_TOKEN_DECOMPOSITION',

            'candidate_score'
                => 0.86,

            'matched_lookup_surface'
                => $surfaceForm,

            'normalized_lookup_surface'
                => prose_character_normalize_surface($surfaceForm),

            'transform_chain'
                => [
                    'normalize_case',
                    'tokenize_surface',
                    'tokenize_canonical_label',
                    'canonical_label_token_match',
                ],

            'resolver_stage'
                => __FUNCTION__,

            'lookup_stage'
                => __FUNCTION__,
        ]);
    }

    return array_values($candidates);
}
