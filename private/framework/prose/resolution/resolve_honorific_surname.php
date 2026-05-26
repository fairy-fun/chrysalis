<?php

declare(strict_types=1);

require_once __DIR__ . '/resolve_normalized_surname_alias.php';
require_once __DIR__ . '/../spans/normalize_surface.php';
require_once __DIR__ . '/../arbitration/candidate_deduper.php';

function prose_character_try_honorific_surname(
    PDO $pdo,
    string $surfaceForm
): array {

    if (
        preg_match(
            "/^(Mr|Mrs|Miss|Ms|Dr)\\.?\\s+([\\p{L}'-]+)$/iu",
            trim($surfaceForm),
            $matches
        ) !== 1
    ) {
        return [];
    }

    $surname = (string)($matches[2] ?? '');
    $candidates = [];

    foreach (
        prose_character_try_normalized_surname_alias($pdo, $surname)
        as $candidate
    ) {

        $candidate['resolution_method_classval_id']
            = 'RESOLUTION_METHOD_HONORIFIC_SURNAME';

        $candidate['candidate_score']
            = 0.88;

        $candidate['matched_lookup_surface']
            = trim($surfaceForm);

        $candidate['normalized_lookup_surface']
            = prose_character_normalize_surface($surname);

        $candidate['resolver_stage']
            = __FUNCTION__;

        $candidate['lookup_stage']
            = 'prose_character_try_normalized_surname_alias';

        $candidate['transform_chain'] = [
            'strip_honorific',
            'extract_surname',
            'normalize_case',
            'normalize_whitespace',
        ];

        prose_character_append_candidate($candidates, $candidate);
    }

    return array_values($candidates);
}
