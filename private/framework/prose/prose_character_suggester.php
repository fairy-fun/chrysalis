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
    ?string $resolverStage = null
): void {

    $entityId = trim($entityId);

    if ($entityId === '') {
        return;
    }

    if (
        !isset($candidates[$entityId])
        || (float)$candidateScore > (float)$candidates[$entityId]['candidate_score']
    ) {
        $candidates[$entityId] = [
            'resolved_entity_id' => $entityId,
            'candidate_label' => $candidateLabel,
            'resolution_method_classval_id' => $resolutionMethodClassvalId,
            'candidate_score' => $candidateScore,
            'scoring_notes' => $scoringNotes,
            'matched_lookup_surface' => $matchedLookupSurface,
            'transform_chain' => $transformChain,
            'resolver_stage' => $resolverStage,
        ];
    }
}

function prose_character_try_exact_canonical_label(PDO $pdo, string $surfaceForm): array
{
    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    $candidates = [];

    try {
        $stmt = $pdo->prepare("
            SELECT id AS entity_id, canonical_label AS candidate_label
            FROM entities
            WHERE entity_type_id = 'entity_type_character'
              AND canonical_label = :surface_form
            LIMIT 10
        ");

        $stmt->execute([
            ':surface_form' => $surfaceForm,
        ]);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            prose_character_append_candidate(
                $candidates,
                trim((string)($row['entity_id'] ?? '')),
                (string)($row['candidate_label'] ?? $surfaceForm),
                'RESOLUTION_METHOD_EXACT_CANONICAL_LABEL',
                1.00,
                'Matched exact canonical entity label.',
                $surfaceForm,
                [],
                __FUNCTION__
            );
        }
    } catch (Throwable $e) {
        return [];
    }

    return $candidates;
}

function prose_character_try_entity_label(PDO $pdo, string $surfaceForm): array
{
    if (!prose_character_table_exists($pdo, 'entity_labels')) {
        return [];
    }

    $candidates = [];

    try {
        $stmt = $pdo->prepare("
            SELECT entity_id, label AS candidate_label
            FROM entity_labels
            WHERE label = :surface_form
            LIMIT 10
        ");

        $stmt->execute([
            ':surface_form' => $surfaceForm,
        ]);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            prose_character_append_candidate(
                $candidates,
                trim((string)($row['entity_id'] ?? '')),
                (string)($row['candidate_label'] ?? $surfaceForm),
                'RESOLUTION_METHOD_EXACT_ALIAS',
                0.95,
                'Matched deterministic entity label.',
                $surfaceForm,
                [],
                __FUNCTION__
            );
        }
    } catch (Throwable $e) {
        return [];
    }

    return $candidates;
}

function prose_character_try_exact_alias(PDO $pdo, string $surfaceForm): array
{
    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '' || !prose_character_table_exists($pdo, 'semantic_aliases')) {
        return [];
    }

    $candidates = [];

    try {
        $stmt = $pdo->prepare("
            SELECT entity_id, alias AS candidate_label
            FROM semantic_aliases
            WHERE alias = :surface_form
            LIMIT 10
        ");

        $stmt->execute([
            ':surface_form' => $surfaceForm,
        ]);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            prose_character_append_candidate(
                $candidates,
                trim((string)($row['entity_id'] ?? '')),
                (string)($row['candidate_label'] ?? $surfaceForm),
                'RESOLUTION_METHOD_EXACT_ALIAS',
                0.96,
                'Matched exact deterministic semantic alias.',
                $surfaceForm,
                [],
                __FUNCTION__
            );
        }
    } catch (Throwable $e) {
        return [];
    }

    return $candidates;
}

function prose_character_try_normalized_alias(PDO $pdo, string $surfaceForm): array
{
    $normalizedSurface = prose_character_normalize_surface($surfaceForm);

    if ($normalizedSurface === '' || !prose_character_table_exists($pdo, 'semantic_aliases')) {
        return [];
    }

    $candidates = [];

    try {
        $stmt = $pdo->prepare("
            SELECT entity_id, alias AS candidate_label
            FROM semantic_aliases
            WHERE LOWER(alias) = :normalized_surface
            LIMIT 10
        ");

        $stmt->execute([
            ':normalized_surface' => $normalizedSurface,
        ]);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            prose_character_append_candidate(
                $candidates,
                trim((string)($row['entity_id'] ?? '')),
                (string)($row['candidate_label'] ?? $surfaceForm),
                'RESOLUTION_METHOD_NORMALIZED_ALIAS',
                0.94,
                'Matched normalized deterministic semantic alias.',
                $normalizedSurface,
                ['normalize_case', 'normalize_whitespace'],
                __FUNCTION__
            );
        }
    } catch (Throwable $e) {
        return [];
    }

    return $candidates;
}

function prose_character_try_normalized_surname_alias(PDO $pdo, string $surname): array
{
    $surname = trim($surname);

    if ($surname === '' || str_contains($surname, ' ')) {
        return [];
    }

    $normalizedSurname = prose_character_normalize_surface($surname);
    $candidates = prose_character_try_normalized_alias($pdo, $normalizedSurname);

    foreach ($candidates as $entityId => $candidate) {
        $candidates[$entityId]['resolution_method_classval_id'] = 'RESOLUTION_METHOD_NORMALIZED_SURNAME_ALIAS';
        $candidates[$entityId]['candidate_score'] = 0.72;
        $candidates[$entityId]['scoring_notes'] = 'Matched normalized surname alias after surname extraction.';
        $candidates[$entityId]['normalized_lookup_surface'] = $normalizedSurname;
        $candidates[$entityId]['transform_chain'] = [
            'extract_surname',
            'normalize_case',
            'normalize_whitespace',
        ];
    }

    return $candidates;
}

function prose_character_try_honorific_surname(PDO $pdo, string $surfaceForm): array
{
    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    if (preg_match('/^(Mr|Mr\.|Mrs|Mrs\.|Miss|Ms|Ms\.|Dr|Dr\.)\s+([\p{L}\'-]+)$/iu', $surfaceForm, $matches) !== 1) {
        return [];
    }

    $surname = trim((string)($matches[2] ?? ''));

    if ($surname === '') {
        return [];
    }

    $candidates = prose_character_try_normalized_surname_alias($pdo, $surname);

    foreach ($candidates as $entityId => $candidate) {
        $candidates[$entityId]['resolution_method_classval_id'] = 'RESOLUTION_METHOD_HONORIFIC_SURNAME';
        $candidates[$entityId]['candidate_score'] = 0.90;
        $candidates[$entityId]['scoring_notes'] = 'Resolved honorific surface by deterministic surname alias lookup.';
        $candidates[$entityId]['raw_surface_text'] = $surfaceForm;
        $candidates[$entityId]['transform_chain'] = [
            'strip_honorific',
            'extract_surname',
            'normalize_case',
            'normalize_whitespace',
        ];
    }

    return $candidates;
}

function prose_character_try_token_decomposition(PDO $pdo, string $surfaceForm): array
{
    return [];
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

        if ($stageCandidates === []) {
            continue;
        }

        foreach ($stageCandidates as $entityId => $candidate) {
            $candidate['arbitration_stage'] = $resolverStage;
            $candidates[$entityId] = $candidate;
        }

        $topCandidate = reset($stageCandidates);

        if (
            is_array($topCandidate)
            && (float)($topCandidate['candidate_score'] ?? 0.0) >= 0.90
        ) {
            break;
        }
    }

    uasort(
        $candidates,
        static fn (array $a, array $b): int => (float)$b['candidate_score'] <=> (float)$a['candidate_score']
    );

    return array_values($candidates);
}

function suggest_prose_characters(PDO $pdo, string $proseBody, array $context = []): array
{
    $proseBody = trim($proseBody);

    if ($proseBody === '') {
        return [
            'suggestions' => [
                'characters' => [],
            ],
            'suggestion_count' => 0,
            'mutates_character_ontology' => false,
        ];
    }

    $suggestionsByEntity = [];
    $unresolved = [];

    foreach (prose_character_known_surface_forms() as $surfaceSet) {
        $surfaceForms = $surfaceSet['surface_forms'] ?? [];

        if (!is_array($surfaceForms)) {
            continue;
        }

        foreach ($surfaceForms as $surfaceForm) {
            $surfaceForm = trim((string)$surfaceForm);

            if ($surfaceForm === '') {
                continue;
            }

            $spans = prose_character_find_surface_spans($proseBody, $surfaceForm);

            if ($spans === []) {
                continue;
            }

            $candidates = prose_character_resolve_surface_form($pdo, $surfaceForm);
            $selectedCandidate = $candidates[0] ?? null;

            foreach ($spans as $span) {
                $evidenceContext = array_merge(
                    $context,
                    [
                        'workflow' => 'calendar_event_suggest_characters',
                        'span_start' => $span['span_start'],
                        'span_end' => $span['span_end'],
                        'source_classval_id' => 'SURFACE_SOURCE_PROSE_TEXT',
                        'confidence_classval_id' => $selectedCandidate === null
                            ? 'CONFIDENCE_LOW'
                            : 'CONFIDENCE_HIGH',
                        'resolution_status_classval_id' => $selectedCandidate === null
                            ? 'EVIDENCE_STATUS_UNRESOLVED'
                            : 'EVIDENCE_STATUS_ADVISORY',
                    ]
                );

                $evidenceId = persist_semantic_surface_evidence(
                    $pdo,
                    (string)$span['surface_text'],
                    $evidenceContext,
                    is_array($selectedCandidate) ? $selectedCandidate : null
                );

                if ($evidenceId !== null && $candidates !== []) {
                    persist_semantic_surface_resolution_candidates(
                        $pdo,
                        $evidenceId,
                        $candidates,
                        is_array($selectedCandidate)
                            ? (string)$selectedCandidate['resolved_entity_id']
                            : null
                    );
                }
            }

            if (is_array($selectedCandidate)) {
                $entityId = (string)$selectedCandidate['resolved_entity_id'];

                if (!isset($suggestionsByEntity[$entityId])) {
                    $suggestionsByEntity[$entityId] = [
                        'resolution_status' => 'resolved',
                        'resolved_entity_id' => $entityId,
                        'candidate_label' => $selectedCandidate['candidate_label'] ?? $surfaceForm,
                        'surface_forms' => [],
                        'candidate_score' => (float)($selectedCandidate['candidate_score'] ?? 0.0),
                        'resolution_method_classval_id' => $selectedCandidate['resolution_method_classval_id'] ?? null,
                        'scoring_notes' => $selectedCandidate['scoring_notes'] ?? null,
                    ];
                }

                $suggestionsByEntity[$entityId]['surface_forms'][] = $surfaceForm;
                $suggestionsByEntity[$entityId]['surface_forms'] = array_values(array_unique(
                    $suggestionsByEntity[$entityId]['surface_forms']
                ));
            } else {
                $unresolved[$surfaceForm] = [
                    'resolution_status' => 'unresolved',
                    'resolved_entity_id' => null,
                    'candidate_label' => $surfaceForm,
                    'surface_forms' => [$surfaceForm],
                    'candidate_score' => 0.0,
                    'resolution_method_classval_id' => 'RESOLUTION_METHOD_UNRESOLVED',
                    'scoring_notes' => 'Deterministic semantic resolution pipeline exhausted without satisfying confidence threshold.',
                ];
            }
        }
    }

    $characters = array_values($suggestionsByEntity);

    usort(
        $characters,
        static fn (array $a, array $b): int => (float)$b['candidate_score'] <=> (float)$a['candidate_score']
    );

    return [
        'suggestions' => [
            'characters' => array_merge($characters, array_values($unresolved)),
        ],
        'suggestion_count' => count($characters),
        'unresolved_surface_count' => count($unresolved),
        'mutates_character_ontology' => false,
        'approval_required' => true,
    ];
}
