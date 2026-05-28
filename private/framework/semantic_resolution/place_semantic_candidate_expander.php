<?php

declare(strict_types=1);

require_once __DIR__
    . '/../prose/prose_place_containment_provider.php';

function place_semantic_candidate_expander_expand(
    PDO $pdo,
    array $directCandidates
): array {

    $expandedCandidates = [];

    foreach ($directCandidates as $candidate) {

        if (!is_array($candidate)) {
            continue;
        }

        $resolvedEntityId = trim((string)(
            $candidate['resolved_entity_id'] ?? ''
        ));

        if ($resolvedEntityId === '') {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Preserve direct semantic evidence candidate
        |--------------------------------------------------------------------------
        */

        $directCandidate = $candidate;

        $directCandidate['semantic_relationship_classval_id']
            = 'SEMANTIC_RELATIONSHIP_DIRECT_MATCH';

        $expandedCandidates[] = $directCandidate;

        /*
        |--------------------------------------------------------------------------
        | Expand containment ancestry candidates
        |--------------------------------------------------------------------------
        */

        $containmentContext =
            prose_place_containment_provider_fetch_context(
                $pdo,
                [$resolvedEntityId]
            );

        $containment = (
            $containmentContext[$resolvedEntityId]
            ?? null
        );

        if (!is_array($containment)) {
            continue;
        }

        $ancestors = array_values(
            $containment['ancestors'] ?? []
        );

        if ($ancestors === []) {
            continue;
        }

        $baseScore = (float)(
            $candidate['candidate_score'] ?? 0.0
        );

        foreach ($ancestors as $depthIndex => $ancestor) {

            if (!is_array($ancestor)) {
                continue;
            }

            $ancestorEntityId = trim((string)(
                $ancestor['place_id']
                ?? $ancestor['entity_id']
                ?? ''
            ));

            if ($ancestorEntityId === '') {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Hierarchical decay semantics
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | depth 1 ancestor:
            |     0.97 × 0.75 = 0.7275
            |
            | depth 2 ancestor:
            |     0.97 × 0.75 × 0.75 = 0.545625
            |
            */

            $depth = $depthIndex + 1;

            $decayFactor = pow(0.75, $depth);

            $ancestorScore = round(
                $baseScore * $decayFactor,
                6
            );

            $expandedCandidates[] = [

                'resolved_entity_id'
                    => $ancestorEntityId,

                'candidate_label'
                    => (
                        $ancestor['place_label']
                        ?? $ancestor['label']
                        ?? $ancestorEntityId
                    ),

                'resolution_method_classval_id'
                    => (
                        $candidate['resolution_method_classval_id']
                        ?? 'RESOLUTION_METHOD_EXACT_PLACE_NAME'
                    ),

                'semantic_relationship_classval_id'
                    => 'SEMANTIC_RELATIONSHIP_CONTAINMENT_ANCESTOR',

                'candidate_score'
                    => $ancestorScore,

                'matched_lookup_surface'
                    => (
                        $candidate['matched_lookup_surface']
                        ?? null
                    ),

                'normalized_lookup_surface'
                    => (
                        $candidate['normalized_lookup_surface']
                        ?? null
                    ),

                'resolver_stage'
                    => 'containment_expansion',

                'lookup_stage'
                    => 'place_containment_hierarchy',

                'transform_chain'
                    => array_merge(
                        (array)(
                            $candidate['transform_chain']
                            ?? []
                        ),
                        [
                            [
                                'transform'
                                    => 'containment_ancestor_expansion',

                                'source_entity_id'
                                    => $resolvedEntityId,

                                'expanded_entity_id'
                                    => $ancestorEntityId,

                                'hierarchy_depth'
                                    => $depth,
                            ],
                        ]
                    ),

                'containment_source_entity_id'
                    => $resolvedEntityId,

                'containment_depth'
                    => $depth,

                'scoring_notes'
                    => sprintf(
                        'Containment ancestor expansion from %s at depth %d with decay factor %.4f',
                        $resolvedEntityId,
                        $depth,
                        $decayFactor
                    ),
            ];
        }
    }

    return $expandedCandidates;
}