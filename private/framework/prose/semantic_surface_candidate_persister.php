<?php

declare(strict_types=1);

function persist_semantic_surface_resolution_candidates(
    PDO $pdo,
    int $semanticSurfaceEvidenceId,
    array $candidates,
    ?string $selectedEntityId = null
): array {

    if ($semanticSurfaceEvidenceId < 1) {
        throw new InvalidArgumentException(
            'semanticSurfaceEvidenceId must be positive'
        );
    }

    $insert = $pdo->prepare('
        INSERT INTO semantic_surface_evidence_candidates (
            semantic_surface_evidence_id,
            candidate_entity_id,
            resolution_method_classval_id,
            candidate_score,
            scoring_notes,
            is_selected
        ) VALUES (
            :semantic_surface_evidence_id,
            :candidate_entity_id,
            :resolution_method_classval_id,
            :candidate_score,
            :scoring_notes,
            :is_selected
        )
    ');

    $persisted = [];

    foreach ($candidates as $candidate) {

        $candidateEntityId = trim((string)(
            $candidate['resolved_entity_id']
            ?? ''
        ));

        if ($candidateEntityId === '') {
            continue;
        }

        $resolutionMethod = trim((string)(
            $candidate['resolution_method_classval_id']
            ?? ''
        ));

        if ($resolutionMethod === '') {
            continue;
        }

        $isSelected = (
            $selectedEntityId !== null
            && $selectedEntityId !== ''
            && $candidateEntityId === $selectedEntityId
        ) ? 1 : 0;

        $insert->execute([
            ':semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
            ':candidate_entity_id' => $candidateEntityId,
            ':resolution_method_classval_id' => $resolutionMethod,
            ':candidate_score' => (float)(
                $candidate['candidate_score'] ?? 0.0
            ),
            ':scoring_notes' => (string)(
                $candidate['scoring_notes'] ?? ''
            ),
            ':is_selected' => $isSelected,
        ]);

        $persisted[] = [
            'semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
            'candidate_entity_id' => $candidateEntityId,
            'resolution_method_classval_id' => $resolutionMethod,
            'candidate_score' => (float)(
                $candidate['candidate_score'] ?? 0.0
            ),
            'is_selected' => $isSelected === 1,
        ];
    }

    return [
        'semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
        'persisted_candidate_count' => count($persisted),
        'persisted_candidates' => $persisted,
    ];
}
