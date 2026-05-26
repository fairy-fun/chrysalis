<?php

declare(strict_types=1);

require_once __DIR__
    . '/semantic_resolution_candidate.php';

function semantic_resolution_stage_result_build(
    string $stageName,
    array $candidates = [],
    array $metadata = []
): array {

    $normalizedCandidates = [];

    foreach ($candidates as $candidate) {

        if (!is_array($candidate)) {
            continue;
        }

        $normalizedCandidates[] =
            semantic_resolution_candidate_build(
                $candidate
            );
    }

    return [
        'stage_name' => trim($stageName),
        'candidate_count' => count(
            $normalizedCandidates
        ),
        'candidates' => $normalizedCandidates,
        'metadata' => $metadata,
    ];
}