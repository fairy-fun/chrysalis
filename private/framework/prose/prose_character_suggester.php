<?php

declare(strict_types=1);

require_once __DIR__ . '/semantic_surface_evidence_persister.php';
require_once __DIR__ . '/semantic_surface_candidate_persister.php';

/**
 * Deterministic character suggestion from prose.
 *
 * This layer is advisory only. It produces reversible, evidence-backed
 * suggestions and must not mutate canonical ontology.
 */

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
            static fn (mixed $token): string => trim((string)$token),
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
        json_encode(
            array_values($transformChain),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ),
        trim((string)($candidate['resolver_stage'] ?? '')),
        trim((string)($candidate['arbitration_stage'] ?? '')),
    ]);
}

function prose_character_find_surface_spans(
    string $proseBody,
    string $surfaceForm
): array {

    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    $pattern = '/(?<![A-Za-z0-9])' . preg_quote($surfaceForm, '/') . '(?![A-Za-z0-9])/iu';

    if (preg_match_all($pattern, $proseBody, $matches, PREG_OFFSET_CAPTURE) !== false) {
        $spans = [];

        foreach (($matches[0] ?? []) as $match) {
            if (!is_array($match) || count($match) < 2) {
                continue;
            }

            $matchedText = (string)$match[0];
            $spanStart = (int)$match[1];

            $spans[] = [
                'surface_text' => $matchedText,
                'span_start' => $spanStart,
                'span_end' => $spanStart + strlen($matchedText),
            ];
        }

        return $spans;
    }

    return [];
}

function prose_character_table_exists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($tableName));
    } catch (Throwable $e) {
        return false;
    }

    return $stmt instanceof PDOStatement
        && is_array($stmt->fetch(PDO::FETCH_NUM));
}

function prose_character_append_candidate(
    array &$candidates,
    string $entityId,
    string $candidateLabel,
    string $resolutionMethodClassvalId,
    float $candidateScore,
    string $scoringNotes,
    string $matchedLookupSurface,
    array $transformChain = [],
    ?string $resolverStage = null,
    ?string $lookupStage = null
): void {

    $entityId = trim($entityId);

    if ($entityId === '') {
        return;
    }

    $candidateIdentityKey = implode('|', [
        $entityId,
        $resolutionMethodClassvalId,
        $resolverStage ?? '',
        json_encode($transformChain),
    ]);

    $candidates[$candidateIdentityKey] = [
        'resolved_entity_id' => $entityId,
        'candidate_label' => $candidateLabel,
        'resolution_method_classval_id' => $resolutionMethodClassvalId,
        'candidate_score' => $candidateScore,
        'scoring_notes' => $scoringNotes,
        'matched_lookup_surface' => $matchedLookupSurface,
        'transform_chain' => $transformChain,
        'resolver_stage' => $resolverStage,
        'lookup_stage' => $lookupStage,
    ];
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

    $candidates = [];

    try {
        $stmt = $pdo->prepare("
            SELECT
                id AS entity_id,
                canonical_label AS candidate_label
            FROM entities
            WHERE entity_type_id = 'entity_type_character'
              AND canonical_label IS NOT NULL
            LIMIT 500
        ");

        $stmt->execute();

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $entityId = trim((string)($row['entity_id'] ?? ''));
            $candidateLabel = trim((string)($row['candidate_label'] ?? ''));

            if ($entityId === '' || $candidateLabel === '') {
                continue;
            }

            $canonicalTokens = prose_character_tokenize_surface($candidateLabel);

            if (count($canonicalTokens) < count($surfaceTokens)) {
                continue;
            }

            $tokenCursor = 0;

            foreach ($canonicalTokens as $canonicalToken) {
                if ($canonicalToken === $surfaceTokens[$tokenCursor]) {
                    $tokenCursor++;

                    if ($tokenCursor >= count($surfaceTokens)) {
                        break;
                    }
                }
            }

            if ($tokenCursor < count($surfaceTokens)) {
                continue;
            }

            prose_character_append_candidate(
                $candidates,
                $entityId,
                $candidateLabel,
                'RESOLUTION_METHOD_TOKEN_DECOMPOSITION',
                0.86,
                'Resolved surface form through deterministic canonical token decomposition.',
                $surfaceForm,
                [
                    'normalize_case',
                    'tokenize_surface',
                    'tokenize_canonical_label',
                    'canonical_label_token_match',
                ],
                __FUNCTION__,
                __FUNCTION__
            );
        }
    } catch (Throwable $e) {
        return [];
    }

    return $candidates;
}

function prose_character_resolve_surface_form(PDO $pdo, string $surfaceForm): array
{
    return [];
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
