<?php

declare(strict_types=1);

function persist_semantic_surface_resolution_candidates(
    PDO $pdo,
    int $semanticSurfaceEvidenceId,
    array $candidates,
    ?string $selectedCandidateIdentityKey = null
): array {

    if ($semanticSurfaceEvidenceId < 1) {
        throw new InvalidArgumentException(
            'semanticSurfaceEvidenceId must be positive'
        );
    }

    $columns = function_exists('semantic_surface_table_columns')
        ? semantic_surface_table_columns(
            $pdo,
            'semantic_surface_resolution_candidates'
        )
        : [];

    if ($columns === []) {
        return [
            'semantic_surface_evidence_id'
                => $semanticSurfaceEvidenceId,

            'persisted_candidate_count'
                => 0,

            'persisted_candidates'
                => [],
        ];
    }

    $persisted = [];
    $seenCandidateKeys = [];

    foreach ($candidates as $candidate) {

        if (!is_array($candidate)) {
            continue;
        }

        $candidateEntityId = trim((string)(
            $candidate['resolved_entity_id']
            ?? $candidate['candidate_entity_id']
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

        $semanticRelationship = trim((string)(
            $candidate['semantic_relationship_classval_id']
            ?? ''
        ));

        if ($semanticRelationship === '') {
            continue;
        }

        $candidateIdentityKey = implode('|', [
            $candidateEntityId,
            $resolutionMethod,
            $semanticRelationship,
        ]);

        if (isset($seenCandidateKeys[$candidateIdentityKey])) {
            continue;
        }

        $seenCandidateKeys[$candidateIdentityKey] = true;

        $isSelected = (
            $selectedCandidateIdentityKey !== null
            && $selectedCandidateIdentityKey !== ''
            && $candidateIdentityKey
                === $selectedCandidateIdentityKey
        ) ? 1 : 0;

        $candidateRow = [
            'semantic_surface_evidence_id'
                => $semanticSurfaceEvidenceId,

            'candidate_entity_id'
                => $candidateEntityId,

            'resolution_method_classval_id'
                => $resolutionMethod,

            'semantic_relationship_classval_id'
                => $semanticRelationship,

            'candidate_score'
                => (float)(
                    $candidate['candidate_score']
                    ?? 0.0
                ),

            'is_selected'
                => $isSelected,

            'scoring_notes'
                => (string)(
                    $candidate['scoring_notes']
                    ?? ''
                ),

            'created_at'
                => date('Y-m-d H:i:s'),
        ];

        $row = function_exists(
            'semantic_surface_build_insertable_row'
        )
            ? semantic_surface_build_insertable_row(
                $columns,
                $candidateRow
            )
            : array_intersect_key(
                $candidateRow,
                $columns
            );

        if (
            !isset($row['semantic_surface_evidence_id'])
            || !isset($row['candidate_entity_id'])
            || !isset($row['resolution_method_classval_id'])
            || !isset($row['semantic_relationship_classval_id'])
            || !isset($row['candidate_score'])
            || !isset($row['is_selected'])
        ) {
            continue;
        }

        $fieldNames = array_keys($row);

        $placeholders = array_map(
            static fn (string $field): string
                => ':' . $field,
            $fieldNames
        );

        $sql =
            'INSERT INTO semantic_surface_resolution_candidates (' .
            implode(', ', $fieldNames) .
            ') VALUES (' .
            implode(', ', $placeholders) .
            ')';

        $insert = $pdo->prepare($sql);

        $params = [];

        foreach ($row as $field => $value) {
            $params[':' . $field] = $value;
        }

        $insert->execute($params);

        $persisted[] = [
            'semantic_surface_evidence_id'
                => $semanticSurfaceEvidenceId,

            'candidate_entity_id'
                => $candidateEntityId,

            'resolution_method_classval_id'
                => $resolutionMethod,

            'semantic_relationship_classval_id'
                => $semanticRelationship,

            'candidate_score'
                => (float)(
                    $candidate['candidate_score']
                    ?? 0.0
                ),

            'is_selected'
                => ($isSelected === 1),

            'scoring_notes'
                => (string)(
                    $candidate['scoring_notes']
                    ?? ''
                ),
        ];
    }

    return [
        'semantic_surface_evidence_id'
            => $semanticSurfaceEvidenceId,

        'persisted_candidate_count'
            => count($persisted),

        'persisted_candidates'
            => $persisted,
    ];
}