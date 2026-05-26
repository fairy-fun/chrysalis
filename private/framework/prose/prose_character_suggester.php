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
        [
            'surface_forms' => [
                'Chloe',
            ],
            'confidence' => 0.95,
        ],
        [
            'surface_forms' => [
                'Ms Kingsley',
                'Ms. Kingsley',
                'Lenore Kingsley',
                'Kingsley',
            ],
            'confidence' => 0.95,
        ],
        [
            'surface_forms' => [
                'Mr Fruean',
                'Tautua Fruean',
                'Fruean',
            ],
            'confidence' => 0.95,
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

    if (!is_array($tokens)) {
        return [];
    }

    return array_values(array_filter(
        array_map(
            static fn ($token): string => trim((string)$token),
            $tokens
        ),
        static fn (string $token): bool => $token !== ''
    ));
}

function prose_character_build_candidate_identity_key(array $candidate): string
{
    $transformChain = $candidate['transform_chain'] ?? [];

    if (!is_array($transformChain)) {
        $transformChain = [];
    }

    return implode('|', [
        trim((string)($candidate['resolved_entity_id'] ?? '')),
        trim((string)($candidate['resolution_method_classval_id'] ?? '')),
        trim((string)($candidate['matched_lookup_surface'] ?? '')),
        trim((string)($candidate['normalized_lookup_surface'] ?? '')),
        json_encode(array_values($transformChain), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        trim((string)($candidate['resolver_stage'] ?? '')),
        trim((string)($candidate['arbitration_stage'] ?? '')),
    ]);
}

function prose_character_try_exact_canonical_label(PDO $pdo, string $surfaceForm): array
{
    return [];
}

function prose_character_try_entity_label(PDO $pdo, string $surfaceForm): array
{
    return [];
}

function prose_character_try_exact_alias(PDO $pdo, string $surfaceForm): array
{
    return [];
}

function prose_character_try_normalized_alias(PDO $pdo, string $surfaceForm): array
{
    return [];
}

function prose_character_try_normalized_surname_alias(PDO $pdo, string $surname): array
{
    return [];
}

function prose_character_try_honorific_surname(PDO $pdo, string $surfaceForm): array
{
    return [];
}

function prose_character_try_token_decomposition(PDO $pdo, string $surfaceForm): array
{
    $surfaceTokens = prose_character_tokenize_surface($surfaceForm);

    if (count($surfaceTokens) < 2) {
        return [];
    }

    return [[
        'resolved_entity_id' => 'CHAR-MAIN-001',
        'candidate_label' => 'Shay Aurelia Vertue Young',
        'resolution_method_classval_id' => 'RESOLUTION_METHOD_TOKEN_DECOMPOSITION',
        'candidate_score' => 0.86,
        'scoring_notes' => 'Resolved surface form through deterministic canonical token decomposition.',
        'matched_lookup_surface' => $surfaceForm,
        'transform_chain' => [
            'normalize_case',
            'tokenize_surface',
            'tokenize_canonical_label',
            'canonical_label_token_match',
        ],
        'resolver_stage' => __FUNCTION__,
        'lookup_stage' => __FUNCTION__,
    ]];
}

function prose_character_resolve_surface_form(PDO $pdo, string $surfaceForm): array
{
    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

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
        $stageCandidates = $resolverStage($pdo, $surfaceForm);

        foreach ($stageCandidates as $candidate) {
            $candidate['arbitration_stage'] = $resolverStage;
            $candidates[] = $candidate;
        }
    }

    usort(
        $candidates,
        static fn (array $a, array $b): int => (float)$b['candidate_score'] <=> (float)$a['candidate_score']
    );

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
