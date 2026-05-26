<?php

declare(strict_types=1);

require_once __DIR__
    . '/semantic_resolution_candidate.php';

function semantic_candidate_arbitrator_run(
    array $candidates,
    array $options = []
): array {

    $normalized = [];

    foreach ($candidates as $candidate) {

        if (!is_array($candidate)) {
            continue;
        }

        $normalized[] =
            semantic_resolution_candidate_build(
                $candidate
            );
    }

    usort(
        $normalized,
        static fn (array $a, array $b): int =>
            (float)($b['candidate_score'] ?? 0.0)
            <=>
            (float)($a['candidate_score'] ?? 0.0)
    );

    $selected = null;

    foreach ($normalized as $index => $candidate) {

        $candidate['arbitration_stage'] = trim((string)(
            $options['arbitration_stage']
            ?? 'semantic_candidate_arbitration'
        ));

        $candidate['is_selected'] =
            $index === 0
                ? 1
                : 0;

        $normalized[$index] = $candidate;

        if ($index === 0) {
            $selected = $candidate;
        }
    }

    return [
        'selected_candidate' => $selected,
        'all_candidates' => $normalized,
    ];
}