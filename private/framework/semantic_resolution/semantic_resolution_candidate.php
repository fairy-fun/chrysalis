<?php

declare(strict_types=1);

function semantic_resolution_candidate_build(
    array $candidate
): array {

    return [

        'resolved_entity_id' => trim((string)(
            $candidate['resolved_entity_id'] ?? ''
        )),

        'candidate_label' => trim((string)(
            $candidate['candidate_label'] ?? ''
        )),

        'resolution_method_classval_id' => trim((string)(
            $candidate['resolution_method_classval_id']
            ?? ''
        )),

        'candidate_score' => (float)(
            $candidate['candidate_score'] ?? 0.0
        ),

        'matched_lookup_surface' => trim((string)(
            $candidate['matched_lookup_surface']
            ?? ''
        )),

        'normalized_lookup_surface' => trim((string)(
            $candidate['normalized_lookup_surface']
            ?? ''
        )),

        'transform_chain' => is_array(
            $candidate['transform_chain'] ?? null
        )
            ? array_values($candidate['transform_chain'])
            : [],

        'resolver_stage' => trim((string)(
            $candidate['resolver_stage'] ?? ''
        )),

        'arbitration_stage' => trim((string)(
            $candidate['arbitration_stage'] ?? ''
        )),

        'lookup_stage' => trim((string)(
            $candidate['lookup_stage'] ?? ''
        )),

        'is_selected' => (int)(
            $candidate['is_selected'] ?? 0
        ),
    ];
}