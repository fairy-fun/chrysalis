<?php

declare(strict_types=1);

require_once __DIR__ . '/../arbitration/candidate_deduper.php';
require_once __DIR__ . '/../spans/normalize_surface.php';

function prose_character_try_exact_alias(
    PDO $pdo,
    string $surfaceForm
): array {

    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT entity_id, alias
             FROM semantic_aliases
             WHERE alias = :surface
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
                => trim((string)($row['entity_id'] ?? '')),

            'candidate_label'
                => (string)($row['alias'] ?? $surfaceForm),

            'resolution_method_classval_id'
                => 'RESOLUTION_METHOD_EXACT_ALIAS',

            'candidate_score'
                => 0.96,

            'matched_lookup_surface'
                => $surfaceForm,

            'normalized_lookup_surface'
                => prose_character_normalize_surface($surfaceForm),

            'transform_chain'
                => [],

            'resolver_stage'
                => __FUNCTION__,

            'lookup_stage'
                => __FUNCTION__,
        ]);
    }

    return array_values($candidates);
}
