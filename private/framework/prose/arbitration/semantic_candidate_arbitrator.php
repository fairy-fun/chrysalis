<?php

declare(strict_types=1);

function semantic_candidate_arbitrator_run(
    array $candidates,
    array $options = []
): array {

    if ($candidates === []) {
        return [
            'selected_candidate' => null,
            'all_candidates' => [],
        ];
    }

    $arbitrationStage = trim((string)(
        $options['arbitration_stage']
        ?? 'semantic_candidate_arbitration'
    ));

    /*
     * Deterministic arbitration ordering.
     *
     * Priority:
     * 1. Higher candidate_score
     * 2. Canonical ontology surfaces
     * 3. Stable lexical entity ordering
     */
    usort(
        $candidates,
        static function (
            array $a,
            array $b
        ): int {

            $scoreComparison =
                ((float)(
                    $b['candidate_score'] ?? 0.0
                )) <=>
                ((float)(
                    $a['candidate_score'] ?? 0.0
                ));

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            $aMethod = trim((string)(
                $a['resolution_method_classval_id']
                ?? ''
            ));

            $bMethod = trim((string)(
                $b['resolution_method_classval_id']
                ?? ''
            ));

            $aCanonical =
                str_contains(
                    $aMethod,
                    'CANONICAL'
                ) ? 1 : 0;

            $bCanonical =
                str_contains(
                    $bMethod,
                    'CANONICAL'
                ) ? 1 : 0;

            $canonicalComparison =
                $bCanonical <=> $aCanonical;

            if ($canonicalComparison !== 0) {
                return $canonicalComparison;
            }

            return strcmp(
                trim((string)(
                    $a['resolved_entity_id']
                    ?? ''
                )),
                trim((string)(
                    $b['resolved_entity_id']
                    ?? ''
                ))
            );
        }
    );

    $selectedCandidate = null;

    foreach ($candidates as $index => &$candidate) {

        if (!is_array($candidate)) {
            continue;
        }

        $candidate['arbitration_stage'] =
            $arbitrationStage;

        $candidate['arbitration_rank'] =
            $index + 1;

        $candidate['is_selected'] =
            $index === 0 ? 1 : 0;

        /*
         * Preserve explicit loser lineage.
         */
        $candidate['selection_state'] =
            $index === 0
                ? 'SELECTED'
                : 'LOSER';

        if ($index === 0) {
            $selectedCandidate = $candidate;
        }
    }

    unset($candidate);

    return [
        'selected_candidate' => $selectedCandidate,
        'all_candidates' => array_values($candidates),
    ];
}