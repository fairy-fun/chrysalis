<?php

declare(strict_types=1);

require_once __DIR__ . '/../arbitration/candidate_deduper.php';
require_once __DIR__ . '/../spans/normalize_surface.php';

function prose_character_try_exact_canonical_label(
    PDO $pdo,
    string $surfaceForm
): array {

    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT
                e.id,
                el.label AS canonical_label
             FROM entity_labels el
             INNER JOIN entities e
                 ON e.id = el.entity_id
             WHERE e.entity_type_id = 'entity_type_character'
               AND el.label = :surface
             LIMIT 10"
        );

        $stmt->execute([
            ':surface' => $surfaceForm,
        ]);
    } catch (Throwable $e) {
        return [];
    }

    $candidates = [];

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        prose_character_append_candidate($candidates, [
            'resolved_entity_id'
                => trim((string)($row['id'] ?? '')),

            'candidate_label'
                => (string)($row['canonical_label'] ?? $surfaceForm),

            'resolution_method_classval_id'
                => 'RESOLUTION_METHOD_EXACT_CANONICAL_LABEL',

            'candidate_score'
                => 1.00,

            'matched_lookup_surface'
                => $surfaceForm,

            'normalized_lookup_surface'
                => prose_character_normalize_surface($surfaceForm),

            'transform_chain'
                => [
                    'entity_labels_canonical_lookup',
                ],

            'resolver_stage'
                => __FUNCTION__,

            'lookup_stage'
                => __FUNCTION__,
        ]);
    }

    return array_values($candidates);
}
