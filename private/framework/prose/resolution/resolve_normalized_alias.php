<?php

declare(strict_types=1);

require_once __DIR__ . '/../arbitration/candidate_deduper.php';
require_once __DIR__ . '/../spans/normalize_surface.php';

function prose_character_try_normalized_alias(
    PDO $pdo,
    string $surfaceForm
): array {

    $normalizedSurface = prose_character_normalize_surface($surfaceForm);

    if ($normalizedSurface === '') {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT entity_id, alias
             FROM semantic_aliases
             WHERE LOWER(alias) = :surface
             LIMIT 10"
        );

        $stmt->execute([
            ':surface' => $normalizedSurface,
        ]);
    } catch (Throwable $e) {
        return [];
    }

    $candidates = [];

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        prose_character_append_candidate($candidates, [
            'resolved_entity_id'
                => trim((string)($row['entity_id'] ?? '')),

            'candidate_label'
                => (string)($row['alias'] ?? $surfaceForm),

            'resolution_method_classval_id'
                => 'RESOLUTION_METHOD_NORMALIZED_ALIAS',

            'candidate_score'
                => 0.94,

            'matched_lookup_surface'
                => (string)($row['alias'] ?? $surfaceForm),

            'normalized_lookup_surface'
                => $normalizedSurface,

            'transform_chain'
                => [
                    'normalize_case',
                    'normalize_whitespace',
                ],

            'resolver_stage'
                => __FUNCTION__,

            'lookup_stage'
                => __FUNCTION__,
        ]);
    }

    return array_values($candidates);
}
