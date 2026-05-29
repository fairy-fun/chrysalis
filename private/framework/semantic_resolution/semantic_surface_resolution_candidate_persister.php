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

function persist_semantic_surface_resolution_candidates(
PDO $pdo,
int $semanticSurfaceEvidenceId,
array $candidates,
?string $selectedCandidateIdentityKey = null
): array {

$inserted = [];
$count = 0;

foreach ($candidates as $candidate) {

    // Resolve identity key for selection
    $identityKey = implode('|', [
        trim((string)($candidate['resolved_entity_id'] ?? $candidate['candidate_entity_id'] ?? '')),
        trim((string)($candidate['resolution_method_classval_id'] ?? '')),
        trim((string)($candidate['semantic_relationship_classval_id'] ?? '')),
    ]);

    $isSelected = ($selectedCandidateIdentityKey !== null)
        && ($identityKey === $selectedCandidateIdentityKey);

    $sql = "
        INSERT INTO semantic_surface_resolution_candidates (
            semantic_surface_evidence_id,
            candidate_entity_id,
            resolution_method_classval_id,
            semantic_relationship_classval_id,
            candidate_score,
            is_selected,
            scoring_notes,
            created_at
        ) VALUES (
            :evidence_id,
            :candidate_entity_id,
            :method_id,
            :relationship_id,
            :score,
            :is_selected,
            :notes,
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);

    $ok = $stmt->execute([
        'evidence_id' => $semanticSurfaceEvidenceId,
        'candidate_entity_id' => $candidate['resolved_entity_id'] ?? $candidate['candidate_entity_id'] ?? null,
        'method_id' => $candidate['resolution_method_classval_id'] ?? null,
        'relationship_id' => $candidate['semantic_relationship_classval_id'] ?? null,
        'score' => $candidate['candidate_score'] ?? 0,
        'is_selected' => $isSelected ? 1 : 0,
        'notes' => $candidate['scoring_notes'] ?? null,
    ]);

    if ($ok) {
        $count++;
        $inserted[] = $identityKey;
    }
}

return [
    'semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
    'persisted_candidate_count' => $count,
    'persisted_candidates' => $inserted,
];

}