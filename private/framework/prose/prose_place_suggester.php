<?php

declare(strict_types=1);

require_once __DIR__
    . '/prose_place_surface_inventory_provider.php';

require_once __DIR__
    . '/prose_place_resolution_workflow.php';

require_once __DIR__
    . '/spans/find_surface_spans.php';

function suggest_prose_places(
    PDO $pdo,
    string $proseBody,
    array $context = []
): array {

    $suggestionsByPlace = [];

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

        $selectedCandidate = (
            $resolution['selected_candidate']
            ?? null
        );

        if (!is_array($selectedCandidate)) {
            continue;
        }

        $placeId = trim((string)(
            $selectedCandidate['resolved_entity_id'] ?? ''
        ));

        if ($placeId === '') {
            continue;
        }

        $existingScore = (float)(
            $suggestionsByPlace[$placeId]['candidate_score'] ?? -1
        );

        $incomingScore = (float)(
            $selectedCandidate['candidate_score'] ?? 0.0
        );

        if ($existingScore >= $incomingScore) {
            continue;
        }

        $suggestionsByPlace[$placeId] = [
            'resolution_status' => 'resolved',
            'resolved_place_id' => $placeId,
            'candidate_label' => (
                $selectedCandidate['candidate_label']
                ?? $surface
            ),
            'surface_forms' => [$surface],
            'surface_spans' => $surfaceSpans,
            'candidate_score' => $incomingScore,
            'resolution_method_classval_id' => (
                $selectedCandidate['resolution_method_classval_id']
                ?? null
            ),
            'resolver_stage' => (
                $selectedCandidate['resolver_stage']
                ?? null
            ),
            'arbitration_stage' => (
                $selectedCandidate['arbitration_stage']
                ?? null
            ),
        ];
    }

    return [
        'suggestions' => [
            'places' => array_values($suggestionsByPlace),
        ],
        'suggestion_count' => count($suggestionsByPlace),
        'mutates_place_ontology' => false,
        'approval_required' => true,
    ];
}
