<?php

declare(strict_types=1);

require_once __DIR__
    . '/../semantic_resolution/semantic_resolution_workflow_runner.php';

function prose_place_resolution_workflow_run(
    PDO $pdo,
    array $placeSurfaceRecord
): array {

    $surface = trim((string)(
        $placeSurfaceRecord['surface'] ?? ''
    ));

    $placeId = trim((string)(
        $placeSurfaceRecord['place_id'] ?? ''
    ));

    if (
        $surface === ''
        || $placeId === ''
    ) {
        return [
            'selected_candidate' => null,
            'all_candidates' => [],
            'persistence' => [],
        ];
    }

    $surfaceConfidence = (float)(
        $placeSurfaceRecord['surface_confidence'] ?? 0.9
    );

    return semantic_resolution_workflow_runner_run(
        $pdo,
        [
            [
                'resolver_stage' =>
                    'exact_place_name',

                'candidates' => [
                    [
                        'resolved_entity_id' => $placeId,
                        'candidate_label' => $surface,
                        'resolution_method_classval_id' =>
                            'RESOLUTION_METHOD_EXACT_PLACE_NAME',
                        'candidate_score' => $surfaceConfidence,
                        'matched_lookup_surface' => mb_strtolower($surface),
                        'normalized_lookup_surface' => mb_strtolower($surface),
                        'resolver_stage' => 'exact_place_name',
                        'lookup_stage' => 'place_surface_inventory',
                        'transform_chain' => [],
                    ],
                ],
            ],
        ],
        [
            'arbitration_stage' =>
                'prose_place_resolution',
        ]
    );
}
