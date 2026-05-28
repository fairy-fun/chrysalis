<?php

declare(strict_types=1);

require_once __DIR__
    . '/prose_place_surface_inventory_provider.php';

require_once __DIR__
    . '/prose_place_resolution_workflow.php';

require_once __DIR__
    . '/prose_place_containment_provider.php';

require_once __DIR__
    . '/spans/find_surface_spans.php';

function suggest_prose_places(
    PDO $pdo,
    string $proseBody,
    array $context = []
): array {

    $suggestionsByPlace = [];
    $directlyEvidencedPlaceIds = [];

    foreach (
        prose_place_surface_inventory_provider_fetch_surfaces(
            $pdo
        )
        as $surfaceRecord
    ) {

        if (!is_array($surfaceRecord)) {
            continue;
        }

        $surface = trim((string)(
            $surfaceRecord['surface'] ?? ''
        ));

        if ($surface === '') {
            continue;
        }

        $surfaceSpans = prose_character_find_surface_spans(
            $proseBody,
            $surface
        );

        if ($surfaceSpans === []) {
            continue;
        }

        $resolution = prose_place_resolution_workflow_run(
            $pdo,
            $surfaceRecord
        );

        $allCandidates = (
            $resolution['all_candidates']
            ?? []
        );

        foreach ($allCandidates as $candidate) {

            if (!is_array($candidate)) {
                continue;
            }

            $placeId = trim((string)(
                $candidate['resolved_entity_id'] ?? ''
            ));

            if ($placeId === '') {
                continue;
            }

            $relationshipClassvalId = trim((string)(
                $candidate['semantic_relationship_classval_id']
                ?? 'SEMANTIC_RELATIONSHIP_DIRECT_MATCH'
            ));

            /*
            |--------------------------------------------------------------------------
            | Preserve directly evidenced places separately from advisory expansion
            |--------------------------------------------------------------------------
            */

            if (
                $relationshipClassvalId
                === 'SEMANTIC_RELATIONSHIP_DIRECT_MATCH'
            ) {
                $directlyEvidencedPlaceIds[] = $placeId;
            }

            /*
            |--------------------------------------------------------------------------
            | Composite semantic uniqueness
            |--------------------------------------------------------------------------
            |
            | Same place may appear under different semantic relationships.
            |
            */

            $candidateKey =
                $placeId
                . '::'
                . $relationshipClassvalId;

            $existingScore = (float)(
                $suggestionsByPlace[$candidateKey]['candidate_score']
                ?? -1
            );

            $incomingScore = (float)(
                $candidate['candidate_score']
                ?? 0.0
            );

            if ($existingScore >= $incomingScore) {
                continue;
            }

            $suggestionsByPlace[$candidateKey] = [

                'resolution_status'
                    => 'resolved',

                'resolved_place_id'
                    => $placeId,

                'candidate_label'
                    => (
                        $candidate['candidate_label']
                        ?? $surface
                    ),

                'surface_forms'
                    => [$surface],

                'surface_spans'
                    => $surfaceSpans,

                'candidate_score'
                    => $incomingScore,

                'resolution_method_classval_id'
                    => (
                        $candidate['resolution_method_classval_id']
                        ?? null
                    ),

                'semantic_relationship_classval_id'
                    => $relationshipClassvalId,

                'resolver_stage'
                    => (
                        $candidate['resolver_stage']
                        ?? null
                    ),

                'arbitration_stage'
                    => (
                        $candidate['arbitration_stage']
                        ?? null
                    ),

                'candidate_relationship_type'
                    => match ($relationshipClassvalId) {

                        'SEMANTIC_RELATIONSHIP_DIRECT_MATCH'
                            => 'direct_match',

                        'SEMANTIC_RELATIONSHIP_CONTAINMENT_ANCESTOR'
                            => 'containment_ancestor',

                        'SEMANTIC_RELATIONSHIP_CONTAINMENT_DESCENDANT'
                            => 'containment_descendant',

                        'SEMANTIC_RELATIONSHIP_IMPLIED_CONTEXT'
                            => 'implied_context',

                        default
                            => 'semantic_candidate',
                    },

                'containment_depth'
                    => (
                        $candidate['containment_depth']
                        ?? 0
                    ),

                'scoring_notes'
                    => (
                        $candidate['scoring_notes']
                        ?? null
                    ),

                'containment_context'
                    => [],
            ];
        }
    }
        return [
            'suggestions' => [
                'places' => array_values($suggestionsByPlace),
            ],

            'suggestion_count'
                => count($suggestionsByPlace),

            'mutates_place_ontology'
                => false,

            'approval_required'
                => true,
        ];
    }
}
