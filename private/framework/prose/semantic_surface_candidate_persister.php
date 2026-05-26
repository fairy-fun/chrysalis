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

    $columns = function_exists('semantic_surface_table_columns')
        ? semantic_surface_table_columns($pdo, 'semantic_surface_evidence_candidates')
        : [];

    if ($columns === []) {
        return [
            'semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
            'persisted_candidate_count' => 0,
            'persisted_candidates' => [],
        ];
    }

    $persisted = [];
    $seenCandidateKeys = [];

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

        $transformChain = $candidate['transform_chain'] ?? [];

        if (!is_array($transformChain)) {
            $transformChain = [];
        }

        $transformChainJson = json_encode(
            array_values($transformChain),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $candidateKey = implode('|', [
            $candidateEntityId,
            $resolutionMethod,
            trim((string)($candidate['matched_lookup_surface'] ?? '')),
            trim((string)($candidate['normalized_lookup_surface'] ?? '')),
            (string)$transformChainJson,
        ]);

        if (isset($seenCandidateKeys[$candidateKey])) {
            continue;
        }

        $seenCandidateKeys[$candidateKey] = true;

        $isSelected = (
            $selectedEntityId !== null
            && $selectedEntityId !== ''
            && $candidateEntityId === $selectedEntityId
        ) ? 1 : 0;

        $candidateRow = [
            'semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
            'candidate_entity_id' => $candidateEntityId,
            'resolution_method_classval_id' => $resolutionMethod,
            'candidate_score' => (float)(
                $candidate['candidate_score'] ?? 0.0
            ),
            'scoring_notes' => (string)(
                $candidate['scoring_notes'] ?? ''
            ),
            'is_selected' => $isSelected,
            'matched_lookup_surface' => $candidate['matched_lookup_surface'] ?? null,
            'normalized_lookup_surface' => $candidate['normalized_lookup_surface'] ?? null,
            'raw_surface_text' => $candidate['raw_surface_text'] ?? null,
            'transform_chain_json' => $transformChainJson,
            'resolver_stage' => $candidate['resolver_stage'] ?? null,
            'arbitration_stage' => $candidate['arbitration_stage'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $row = function_exists('semantic_surface_build_insertable_row')
            ? semantic_surface_build_insertable_row($columns, $candidateRow)
            : array_intersect_key($candidateRow, $columns);

        if (
            !isset($row['semantic_surface_evidence_id'])
            || !isset($row['candidate_entity_id'])
            || !isset($row['resolution_method_classval_id'])
            || !isset($row['candidate_score'])
            || !isset($row['is_selected'])
        ) {
            continue;
        }

        $fieldNames = array_keys($row);
        $placeholders = array_map(
            static fn (string $field): string => ':' . $field,
            $fieldNames
        );

        $sql = 'INSERT INTO semantic_surface_evidence_candidates (' .
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
            'semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
            'candidate_entity_id' => $candidateEntityId,
            'resolution_method_classval_id' => $resolutionMethod,
            'candidate_score' => (float)(
                $candidate['candidate_score'] ?? 0.0
            ),
            'is_selected' => $isSelected === 1,
            'matched_lookup_surface' => $candidate['matched_lookup_surface'] ?? null,
            'normalized_lookup_surface' => $candidate['normalized_lookup_surface'] ?? null,
            'raw_surface_text' => $candidate['raw_surface_text'] ?? null,
            'transform_chain' => array_values($transformChain),
            'resolver_stage' => $candidate['resolver_stage'] ?? null,
            'arbitration_stage' => $candidate['arbitration_stage'] ?? null,
        ];
    }

    return [
        'semantic_surface_evidence_id' => $semanticSurfaceEvidenceId,
        'persisted_candidate_count' => count($persisted),
        'persisted_candidates' => $persisted,
    ];
}
