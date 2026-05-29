<?php

declare(strict_types=1);

/*
 * DEBUG VERSION:
 * This file instruments semantic resolution candidate persistence.
 * It does NOT change data semantics — only exposes failure points.
 */

require_once __DIR__
    . '/semantic_surface_resolution_candidate_persister.php';

function persist_semantic_surface_resolution_candidate_provenance(
    PDO $pdo,
    ?int $semanticSurfaceEvidenceId,
    array $candidates,
    ?array $selectedCandidate = null
): array {

    error_log('[SEM-RES] ENTER provenance');
    error_log('[SEM-RES] evidence_id=' . json_encode($semanticSurfaceEvidenceId));
    error_log('[SEM-RES] candidate_count=' . count($candidates));

    if (
        $semanticSurfaceEvidenceId === null
        || $semanticSurfaceEvidenceId < 1
    ) {
        error_log('[SEM-RES] EARLY EXIT: invalid evidence id');

        return [
            'semantic_surface_evidence_id' => null,
            'persisted_candidate_count' => 0,
            'persisted_candidates' => [],
        ];
    }

    if ($candidates === []) {
        error_log('[SEM-RES] EARLY EXIT: empty candidates');

        return [
            'semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
            'persisted_candidate_count' => 0,
            'persisted_candidates' => [],
        ];
    }

    $selectedCandidateIdentityKey = null;

    if ($selectedCandidate !== null) {
        $selectedCandidateIdentityKey =
            build_semantic_surface_resolution_candidate_identity_key(
                $selectedCandidate
            );
    }

    error_log('[SEM-RES] calling persistence layer');

    $result = persist_semantic_surface_resolution_candidates(
        $pdo,
        $semanticSurfaceEvidenceId,
        $candidates,
        $selectedCandidateIdentityKey
    );

    error_log('[SEM-RES] persistence result=' . json_encode($result));

    return $result;
}