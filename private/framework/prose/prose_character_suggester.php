<?php

declare(strict_types=1);

require_once __DIR__ . '/semantic_surface_evidence_persister.php';
require_once __DIR__ . '/semantic_surface_candidate_persister.php';
require_once __DIR__
    . '/../entity/entity_resolution_candidate_factory.php';

function prose_character_known_surface_forms(): array
{
    return [
        [
            'surface_forms' => [
                'Shay',
                'Shay Vertue',
                'Chloe',
                'Mr Fruean',
                'Fruean',
            ],
            'confidence' => 0.99,
        ],
    ];
}

function prose_character_normalize_surface(string $surfaceForm): string
{
    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return '';
    }

    return preg_replace('/\s+/u', ' ', mb_strtolower($surfaceForm, 'UTF-8')) ?? '';
}

function prose_character_tokenize_surface(string $surfaceForm): array
{
    $normalized = prose_character_normalize_surface($surfaceForm);

    if ($normalized === '') {
        return [];
    }

    $tokens = preg_split('/\s+/u', $normalized);

    return is_array($tokens)
        ? array_values(array_filter($tokens, static fn ($token): bool => trim((string)$token) !== ''))
        : [];
}

function prose_character_build_candidate_identity_key(array $candidate): string
{
    return implode('|', [
        trim((string)($candidate['resolved_entity_id'] ?? '')),
        trim((string)($candidate['resolution_method_classval_id'] ?? '')),
        trim((string)($candidate['matched_lookup_surface'] ?? '')),
        trim((string)($candidate['normalized_lookup_surface'] ?? '')),
        json_encode(array_values(is_array($candidate['transform_chain'] ?? null) ? $candidate['transform_chain'] : []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        trim((string)($candidate['resolver_stage'] ?? '')),
        trim((string)($candidate['arbitration_stage'] ?? '')),
    ]);
}

function prose_character_append_candidate(array &$candidates, array $candidate): void
{
    $identityKey = prose_character_build_candidate_identity_key($candidate);
    $candidates[$identityKey] = $candidate;
}

function prose_character_try_exact_alias(
    PDO $pdo,
    string $surfaceForm
): array {

    return entity_resolution_candidate_factory_from_surface(
        $pdo,
        $surfaceForm,
        [
            'entity_type_id' =>
                'entity_type_character',

            'resolution_method_classval_id' =>
                'RESOLUTION_METHOD_EXACT_ALIAS',

            'resolver_context' =>
                __FUNCTION__,
        ]
    );
}

function prose_character_try_token_decomposition(PDO $pdo, string $surfaceForm): array
{
    $surfaceTokens = prose_character_tokenize_surface($surfaceForm);

    if (count($surfaceTokens) < 2) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, canonical_label FROM entities WHERE entity_type_id = 'entity_type_character' AND canonical_label IS NOT NULL LIMIT 500");
        $stmt->execute();
    } catch (Throwable $e) {
        return [];
    }

    $candidates = [];

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $canonicalLabel = trim((string)($row['canonical_label'] ?? ''));
        $canonicalTokens = prose_character_tokenize_surface($canonicalLabel);

        $cursor = 0;

        foreach ($canonicalTokens as $canonicalToken) {
            if (($surfaceTokens[$cursor] ?? null) === $canonicalToken) {
                $cursor++;
            }
        }

        if ($cursor < count($surfaceTokens)) {
            continue;
        }

        prose_character_append_candidate($candidates, [
            'resolved_entity_id' => trim((string)($row['id'] ?? '')),
            'candidate_label' => $canonicalLabel,
            'resolution_method_classval_id' => 'RESOLUTION_METHOD_TOKEN_DECOMPOSITION',
            'candidate_score' => 0.86,
            'matched_lookup_surface' => $surfaceForm,
            'normalized_lookup_surface' => prose_character_normalize_surface($surfaceForm),
            'transform_chain' => [
                'normalize_case',
                'tokenize_surface',
                'tokenize_canonical_label',
                'canonical_label_token_match',
            ],
            'resolver_stage' => __FUNCTION__,
            'lookup_stage' => __FUNCTION__,
        ]);
    }

    return array_values($candidates);
}

function prose_character_resolve_surface_form(PDO $pdo, string $surfaceForm): array
{
    $candidates = array_merge(
        prose_character_try_exact_alias($pdo, $surfaceForm),
        prose_character_try_token_decomposition($pdo, $surfaceForm)
    );

    usort($candidates, static fn (array $a, array $b): int => (float)$b['candidate_score'] <=> (float)$a['candidate_score']);

    return array_values($candidates);
}

function suggest_prose_characters(PDO $pdo, string $proseBody, array $context = []): array
{
    $suggestionsByEntity = [];
    $unresolved = [];

    foreach (prose_character_known_surface_forms() as $surfaceSet) {
        foreach (($surfaceSet['surface_forms'] ?? []) as $surfaceForm) {
            if (!is_string($surfaceForm) || trim($surfaceForm) === '') {
                continue;
            }

            if (mb_stripos($proseBody, $surfaceForm) === false) {
                continue;
            }

            $candidates = prose_character_resolve_surface_form($pdo, $surfaceForm);
            $selectedCandidate = $candidates[0] ?? null;

            if (is_array($selectedCandidate)) {
                $entityId = (string)($selectedCandidate['resolved_entity_id'] ?? '');

                if ($entityId !== '') {
                    $suggestionsByEntity[$entityId] = [
                        'resolution_status' => 'resolved',
                        'resolved_entity_id' => $entityId,
                        'candidate_label' => $selectedCandidate['candidate_label'] ?? $surfaceForm,
                        'surface_forms' => [$surfaceForm],
                        'candidate_score' => (float)($selectedCandidate['candidate_score'] ?? 0.0),
                        'resolution_method_classval_id' => $selectedCandidate['resolution_method_classval_id'] ?? null,
                    ];
                }
            } else {
                $unresolved[$surfaceForm] = [
                    'resolution_status' => 'unresolved',
                    'resolved_entity_id' => null,
                    'candidate_label' => $surfaceForm,
                    'surface_forms' => [$surfaceForm],
                    'candidate_score' => 0.0,
                    'resolution_method_classval_id' => 'RESOLUTION_METHOD_UNRESOLVED',
                ];
            }
        }
    }

    return [
        'suggestions' => [
            'characters' => array_merge(array_values($suggestionsByEntity), array_values($unresolved)),
        ],
        'suggestion_count' => count($suggestionsByEntity),
        'unresolved_surface_count' => count($unresolved),
        'mutates_character_ontology' => false,
        'approval_required' => true,
    ];
}
