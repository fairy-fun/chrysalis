<?php

declare(strict_types=1);

require_once __DIR__
    . '/semantic_surface_resolution_candidate_persister.php';

function build_semantic_surface_resolution_candidate_identity_key(
    array $candidate
): string {

    return implode('|', [
        trim((string)(
            $candidate['resolved_entity_id']
            ?? $candidate['candidate_entity_id']
            ?? ''
        )),
        trim((string)(
            $candidate['resolution_method_classval_id']
            ?? ''
        )),
        trim((string)(
            $candidate['semantic_relationship_classval_id']
            ?? ''
        )),
    ]);
}

function persist_semantic_surface_resolution_candidate_provenance(
    PDO $pdo,
    ?int $semanticSurfaceEvidenceId,
    array $candidates,
    ?array $selectedCandidate = null
): array {

    if (
        $semanticSurfaceEvidenceId === null
        || $semanticSurfaceEvidenceId < 1
    ) {
        return [
            'semantic_surface_evidence_id' => null,
            'persisted_candidate_count' => 0,
            'persisted_candidates' => [],
        ];
    }

    if ($candidates === []) {
        return [
            'semantic_surface_evidence_id'
                => $semanticSurfaceEvidenceId,

            'persisted_candidate_count'
                => 0,

            'persisted_candidates'
                => [],
        ];
    }

    $selectedCandidateIdentityKey = null;

    if ($selectedCandidate !== null) {
        $selectedCandidateIdentityKey =
            build_semantic_surface_resolution_candidate_identity_key(
                $selectedCandidate
            );
    }

    return persist_semantic_surface_resolution_candidates(
        $pdo,
        $semanticSurfaceEvidenceId,
        $candidates,
        $selectedCandidateIdentityKey
    );
}