<?php

declare(strict_types=1);

require_once __DIR__ . '/entity_surface_provider.php';

function entity_resolution_candidate_factory_from_surface(
    PDO $pdo,
    string $surface,
    array $options = []
): array {

    $surface = trim($surface);

    if ($surface === '') {
        return [];
    }

    $entityTypeId = trim((string)(
        $options['entity_type_id'] ?? ''
    ));

    $resolutionMethodClassvalId = trim((string)(
        $options['resolution_method_classval_id']
        ?? 'resolution_method_exact_lookup'
    ));

    $resolverContext = trim((string)(
        $options['resolver_context']
        ?? 'unknown_resolver'
    ));

    $providerCandidates =
        entity_surface_provider_fetch_exact_surface_candidates(
            $pdo,
            $surface,
            $entityTypeId !== ''
                ? $entityTypeId
                : null
        );

    if ($providerCandidates === []) {
        return [];
    }

    $candidates = [];

    foreach ($providerCandidates as $providerCandidate) {

        if (!is_array($providerCandidate)) {
            continue;
        }

        $entityId = trim((string)(
            $providerCandidate['entity_id'] ?? ''
        ));

        if ($entityId === '') {
            continue;
        }

        $matchedSurfaceType = trim((string)(
            $providerCandidate['matched_surface_type']
            ?? ''
        ));

        $candidateLabel = trim((string)(
            $providerCandidate['candidate_label']
            ?? $surface
        ));

        $surfaceConfidence = (float)(
            $providerCandidate['surface_confidence']
            ?? 0.5
        );

        $candidateScore = $surfaceConfidence;

        /*
         * Canonical ontology surfaces receive
         * slight preference over advisory aliases.
         */
        if ($matchedSurfaceType === 'CANONICAL_LABEL') {
            $candidateScore += 0.05;
        }

        $scoringNotes = [
            'resolver_context' => $resolverContext,
            'ontology_surface_provider' => true,
            'matched_surface_type' => $matchedSurfaceType,
            'matched_surface' => (
                $providerCandidate['matched_surface']
                ?? $surface
            ),
            'surface_confidence' => $surfaceConfidence,
        ];

        /*
         * Preserve strongest candidate instance
         * when multiple ontology surfaces resolve
         * to the same entity.
         */
        if (isset($candidates[$entityId])) {

            $existingScore = (float)(
                $candidates[$entityId]['candidate_score']
                ?? 0.0
            );

            if ($existingScore >= $candidateScore) {
                continue;
            }
        }

        $candidates[$entityId] = [
            'resolved_entity_id' => $entityId,
            'candidate_label' => $candidateLabel,
            'resolution_method_classval_id' =>
                $resolutionMethodClassvalId,
            'candidate_score' => $candidateScore,
            'matched_lookup_surface' => (
                $providerCandidate['matched_lookup_surface']
                ?? mb_strtolower($surface)
            ),
            'scoring_notes' => $scoringNotes,
        ];
    }

    return array_values($candidates);
}