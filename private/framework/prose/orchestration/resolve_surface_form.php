<?php

declare(strict_types=1);

require_once __DIR__ . '/../resolution/resolution_pipeline.php';
require_once __DIR__ . '/../arbitration/candidate_deduper.php';
require_once __DIR__ . '/../arbitration/candidate_sorter.php';

function prose_character_resolve_surface_form(PDO $pdo, string $surfaceForm): array
{
    $candidatesByIdentity = [];

    foreach (prose_character_resolution_pipeline() as $resolverStage) {
        foreach ($resolverStage($pdo, $surfaceForm) as $candidate) {
            $candidate['raw_surface_text'] = $surfaceForm;
            $candidate['arbitration_stage'] = __FUNCTION__;

            prose_character_append_candidate(
                $candidatesByIdentity,
                $candidate
            );
        }
    }

    return prose_character_sort_resolution_candidates(
        array_values($candidatesByIdentity)
    );
}
