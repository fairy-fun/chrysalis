<?php

declare(strict_types=1);

require_once __DIR__ . '/semantic_surface_evidence_persister.php';
require_once __DIR__ . '/semantic_surface_candidate_persister.php';

function prose_character_known_surface_forms(): array
{
    return [
        [
            'surface_forms' => [
                'Shay',
                'Shay Vertue',
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

    $surfaceForm = mb_strtolower($surfaceForm, 'UTF-8');

    return preg_replace('/\s+/u', ' ', $surfaceForm) ?? $surfaceForm;
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
    $transformChain = $candidate['transform_chain'] ?? [];

    return implode('|', [
        trim((string)($candidate['resolved_entity_id'] ?? '')),
        trim((string)($candidate['resolution_method_classval_id'] ?? '')),
        trim((string)($candidate['matched_lookup_surface'] ?? '')),
        trim((string)($candidate['normalized_lookup_surface'] ?? '')),
        json_encode(is_array($transformChain) ? array_values($transformChain) : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        trim((string)($candidate['resolver_stage'] ?? '')),
        trim((string)($candidate['arbitration_stage'] ?? '')),
    ]);
}

function prose_character_append_candidate(array &$candidates,array $candidate): void
{
    $identityKey = prose_character_build_candidate_identity_key($candidate);
    $candidates[$identityKey] = $candidate;
}

function prose_character_try_exact_canonical_label(PDO $pdo, string $surfaceForm): array
{
    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, canonical_label FROM entities WHERE entity_type_id = 'entity_type_character' AND canonical_label = :surface LIMIT 10");
        $stmt->execute([
            ':surface' => $surfaceForm,
        ]);
    } catch (Throwable $e) {
        return [];
    }

    $candidates = [];

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        prose_character_append_candidate($candidates, [
            'resolved_entity_id' => trim((string)($row['id'] ?? '')),
            'candidate_label' => (string)($row['canonical_label'] ?? $surfaceForm),
            'resolution_method_classval_id' => 'RESOLUTION_METHOD_EXACT_CANONICAL_LABEL',
            'candidate_score' => 1.00,
            'matched_lookup_surface' => $surfaceForm,
            'normalized_lookup_surface' => prose_character_normalize_surface($surfaceForm),
            'transform_chain' => [],
            'resolver_stage' => __FUNCTION__,
            'lookup_stage' => __FUNCTION__,
        ]);
    }

    return array_values($candidates);
}

function prose_character_try_exact_alias(PDO $pdo, string $surfaceForm): array
{
    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT entity_id, alias FROM semantic_aliases WHERE alias = :surface LIMIT 10");
        $stmt->execute([
            ':surface' => $surfaceForm,
        ]);
    } catch (Throwable $e) {
        return [];
    }

    $candidates = [];

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        prose_character_append_candidate($candidates, [
            'resolved_entity_id' => trim((string)($row['entity_id'] ?? '')),
            'candidate_label' => (string)($row['alias'] ?? $surfaceForm),
            'resolution_method_classval_id' => 'RESOLUTION_METHOD_EXACT_ALIAS',
            'candidate_score' => 0.96,
            'matched_lookup_surface' => $surfaceForm,
            'normalized_lookup_surface' => prose_character_normalize_surface($surfaceForm),
            'transform_chain' => [],
            'resolver_stage' => __FUNCTION__,
            'lookup_stage' => __FUNCTION__,
        ]);
    }

    return array_values($candidates);
}

function prose_character_try_entity_label(PDO $pdo, string $surfaceForm): array
{
    return prose_character_try_exact_alias($pdo, $surfaceForm);
}

function prose_character_try_normalized_alias(PDO $pdo, string $surfaceForm): array
{
    $normalizedSurface = prose_character_normalize_surface($surfaceForm);

    if ($normalizedSurface === '') {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT entity_id, alias FROM semantic_aliases WHERE LOWER(alias) = :surface LIMIT 10");
        $stmt->execute([
            ':surface' => $normalizedSurface,
        ]);
    } catch (Throwable $e) {
        return [];
    }

    $candidates = [];

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        prose_character_append_candidate($candidates, [
            'resolved_entity_id' => trim((string)($row['entity_id'] ?? '')),
            'candidate_label' => (string)($row['alias'] ?? $surfaceForm),
            'resolution_method_classval_id' => 'RESOLUTION_METHOD_NORMALIZED_ALIAS',
            'candidate_score' => 0.94,
            'matched_lookup_surface' => $surfaceForm,
            'normalized_lookup_surface' => $normalizedSurface,
            'transform_chain' => ['normalize_case', 'normalize_whitespace'],
            'resolver_stage' => __FUNCTION__,
            'lookup_stage' => __FUNCTION__,
        ]);
    }

    return array_values($candidates);
}

function prose_character_try_normalized_surname_alias(PDO $pdo, string $surname): array
{
    return prose_character_try_normalized_alias($pdo, $surname);
}

function prose_character_try_honorific_surname(PDO $pdo, string $surfaceForm): array
{
    if (preg_match('/^(Mr|Mrs|Miss|Ms|Dr)\.?\s+([\p{L}\'-]+)$/iu', trim($surfaceForm), $matches) !== 1) {
        return [];
    }

    return prose_character_try_normalized_surname_alias($pdo, (string)($matches[2] ?? ''));
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
    $pipelineStages = [
        'prose_character_try_exact_canonical_label',
        'prose_character_try_entity_label',
        'prose_character_try_exact_alias',
        'prose_character_try_normalized_alias',
        'prose_character_try_honorific_surname',
        'prose_character_try_token_decomposition',
    ];

    $candidates = [];

    foreach ($pipelineStages as $resolverStage) {
        foreach ($resolverStage($pdo, $surfaceForm) as $candidate) {
            $candidate['arbitration_stage'] = $resolverStage;
            $candidates[] = $candidate;
        }
    }

    usort($candidates, static fn (array $a, array $b): int => (float)$b['candidate_score'] <=> (float)$a['candidate_score']);

    return array_values($candidates);
}

function suggest_prose_characters(PDO $pdo, string $proseBody, array $context = []): array
{
    return [
        'suggestions' => [
            'characters' => [],
        ],
        'suggestion_count' => 0,
        'unresolved_surface_count' => 0,
        'mutates_character_ontology' => false,
        'approval_required' => true,
    ];
}
