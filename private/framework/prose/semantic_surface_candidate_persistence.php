```php
<?php

declare(strict_types=1);

function build_semantic_surface_candidate_identity_key(
    array $candidate
): string {

    $transformChain = $candidate['transform_chain'] ?? [];

    if (!is_array($transformChain)) {
        $transformChain = [];
    }

    $transformChainJson = json_encode(
        array_values($transformChain),
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    return implode('|', [
        trim((string)(
            $candidate['resolved_entity_id']
            ?? ''
        )),
        trim((string)(
            $candidate['resolution_method_classval_id']
            ?? ''
        )),
        trim((string)(
            $candidate['matched_lookup_surface']
            ?? ''
        )),
        trim((string)(
            $candidate['normalized_lookup_surface']
            ?? ''
        )),
        (string)$transformChainJson,
        trim((string)(
            $candidate['resolver_stage']
            ?? ''
        )),
        trim((string)(
            $candidate['arbitration_stage']
            ?? ''
        )),
    ]);
}

function persist_semantic_surface_candidate_provenance(
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
            'persisted_candidate_count' => 0,
            'persisted_candidates' => [],
        ];
    }

    $selectedCandidateIdentityKey = null;

    if ($selectedCandidate !== null) {
        $selectedCandidateIdentityKey =
            build_semantic_surface_candidate_identity_key(
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
```
