<?php

declare(strict_types=1);

require_once __DIR__ . '/resolve_normalized_alias.php';
require_once __DIR__ . '/../arbitration/candidate_deduper.php';

function prose_character_try_normalized_surname_alias(
    PDO $pdo,
    string $surname
): array {

    $candidates = [];

    foreach (
        prose_character_try_normalized_alias($pdo, $surname)
        as $candidate
    ) {

        $candidate['resolution_method_classval_id']
            = 'RESOLUTION_METHOD_NORMALIZED_SURNAME_ALIAS';

        $candidate['candidate_score']
            = 0.90;

        $candidate['resolver_stage']
            = __FUNCTION__;

        $candidate['lookup_stage']
            = 'prose_character_try_normalized_alias';

        $candidate['transform_chain'] = [
            'extract_surname',
            'normalize_case',
            'normalize_whitespace',
        ];

        prose_character_append_candidate($candidates, $candidate);
    }

    return array_values($candidates);
}
