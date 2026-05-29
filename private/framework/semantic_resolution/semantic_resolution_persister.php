<?php

declare(strict_types=1);

require_once __DIR__
    . '/semantic_surface_resolution_candidate_persister.php';

function semantic_resolution_persister_persist(
    PDO $pdo,
    array $payload
): array {

    $arbitration = is_array(
        $payload['arbitration'] ?? null
    )
        ? $payload['arbitration']
        : [];

    $semanticSurfaceEvidenceId = (int)(
        $payload['semantic_surface_evidence_id']
        ?? 0
    );

    if ($semanticSurfaceEvidenceId < 1) {
        return [
            'ok' => false,
            'persisted' => false,
            'reason'
                => 'missing_semantic_surface_evidence_id',
        ];
    }

    $allCandidates = is_array(
        $arbitration['all_candidates'] ?? null
    )
        ? $arbitration['all_candidates']
        : [];

    $selectedCandidate = is_array(
        $arbitration['selected_candidate'] ?? null
    )
        ? $arbitration['selected_candidate']
        : null;

    $selectedCandidateIdentityKey = null;

    if ($selectedCandidate !== null) {

        $selectedCandidateIdentityKey = implode('|', [
            trim((string)(
                $selectedCandidate['resolved_entity_id']
                ?? ''
            )),

            trim((string)(
                $selectedCandidate['resolution_method_classval_id']
                ?? ''
            )),

            trim((string)(
                $selectedCandidate['semantic_relationship_classval_id']
                ?? ''
            )),
        ]);
    }

    $ontologyPersistence =
        persist_semantic_surface_resolution_candidates(
            $pdo,
            $semanticSurfaceEvidenceId,
            $allCandidates,
            $selectedCandidateIdentityKey
        );

    return [
        'ok' => true,

        'persisted' => true,

        'semantic_surface_evidence_id'
            => $semanticSurfaceEvidenceId,

        'ontology_candidate_persistence'
            => $ontologyPersistence,
    ];
}