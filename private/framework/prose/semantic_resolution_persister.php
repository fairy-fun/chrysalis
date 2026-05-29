<?php

declare(strict_types=1);

/*
 * Persistence layer doctrine:
 *
 * This layer is responsible ONLY for coherent persistence.
 *
 * It MUST:
 * - persist evidence roots
 * - persist all candidates
 * - preserve loser candidates
 * - preserve arbitration lineage
 * - preserve transform lineage
 * - enforce selected-candidate integrity
 *
 * It MUST NOT:
 * - derive semantics
 * - perform arbitration
 * - mutate resolver scoring
 * - invent transform chains
 * - perform ontology lookup
 * - reinterpret resolver intent
 *
 * Resolver layers produce semantic meaning.
 * Arbitration layers select outcomes.
 * Persistence layers preserve lineage faithfully.
 *
 * This separation prevents "god persister" collapse.
 */

require_once __DIR__
    . '/semantic_surface_evidence_persister.php';

require_once __DIR__
    . '/semantic_surface_candidate_persister.php';

require_once __DIR__
    . '/../semantic_resolution/semantic_surface_resolution_candidate_persistence.php';

function semantic_resolution_persister_persist_resolution(
    PDO $pdo,
    array $resolution
): array {

    $evidencePayload = is_array(
        $resolution['evidence'] ?? null
    )
        ? $resolution['evidence']
        : [];

    $candidatePayloads = is_array(
        $resolution['candidates'] ?? null
    )
        ? array_values($resolution['candidates'])
        : [];

    if ($evidencePayload === []) {
        return [
            'ok' => false,
            'semantic_surface_evidence_id' => null,
            'persisted_candidate_count' => 0,
            'error' => 'Missing evidence payload.',
        ];
    }

    /*
     * Persist evidence root.
     */
    $semanticSurfaceEvidenceId =
        semantic_surface_evidence_persister_insert(
            $pdo,
            $evidencePayload
        );

    if (
        !is_string($semanticSurfaceEvidenceId)
        || trim($semanticSurfaceEvidenceId) === ''
    ) {
        return [
            'ok' => false,
            'semantic_surface_evidence_id' => null,
            'persisted_candidate_count' => 0,
            'error' => 'Failed to persist semantic surface evidence.',
        ];
    }

    $persistedCandidateCount = 0;

    $selectedCandidate = null;

    foreach ($candidatePayloads as $candidatePayload) {

        if (
            (int)(
                $candidatePayload['is_selected']
                ?? 0
            ) === 1
        ) {
            $selectedCandidate = $candidatePayload;
            break;
        }
    }

    /*
     * Persist ALL candidates.
     *
     * Loser candidates are first-class audit lineage.
     */
    foreach ($candidatePayloads as $candidatePayload) {

        if (!is_array($candidatePayload)) {
            continue;
        }

        $candidatePayload[
            'semantic_surface_evidence_id'
        ] = $semanticSurfaceEvidenceId;

        semantic_surface_candidate_persister_insert(
            $pdo,
            $candidatePayload
        );

        $persistedCandidateCount++;
    }

persist_semantic_surface_resolution_candidate_provenance(
    $pdo,
    (int)$semanticSurfaceEvidenceId,
    $candidatePayloads,
    $selectedCandidate
);

    /*
     * Arbitration integrity:
     * never allow multiple selected candidates.
     */
    $selectedCount = 0;

    foreach ($candidatePayloads as $candidatePayload) {

        if (
            (int)(
                $candidatePayload['is_selected']
                ?? 0
            ) === 1
        ) {
            $selectedCount++;
        }
    }

    if ($selectedCount > 1) {

        throw new RuntimeException(
            'Semantic arbitration integrity violation: '
            . 'multiple selected candidates detected.'
        );
    }

    return [
        'ok' => true,
        'semantic_surface_evidence_id' =>
            $semanticSurfaceEvidenceId,

        'persisted_candidate_count' =>
            $persistedCandidateCount,
    ];
}