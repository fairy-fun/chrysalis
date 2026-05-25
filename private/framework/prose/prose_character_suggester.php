<?php

declare(strict_types=1);

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
    ];
}

function prose_character_find_offsets(
    string $proseBody,
    string $surfaceForm
): array {

    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    $escaped = preg_quote($surfaceForm, '/');

    $matched = preg_match_all(
        '/(?<![\p{L}\p{N}_])' . $escaped . '(?![\p{L}\p{N}_])/iu',
        $proseBody,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    if ($matched === false || $matched < 1) {
        return [];
    }

    $offsets = [];

    foreach ($matches[0] as $match) {
        $matchedText = (string)($match[0] ?? '');
        $position = (int)($match[1] ?? 0);

        $offsets[] = [
            'start' => $position,
            'end' => $position + strlen($matchedText),
        ];
    }

    return $offsets;
}

function prose_character_normalized_key(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace('.', '', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return is_string($value) ? trim($value) : '';
}

function prose_character_extract_honorific_surname(string $surfaceText): ?array
{
    $surfaceText = trim($surfaceText);

    if ($surfaceText === '') {
        return null;
    }

    $matched = preg_match(
        '/^(mrs\.?|ms\.?|miss|mr\.?|sir|lady|lord|dr\.?)\s+([\p{L}\'-]+)$/iu',
        $surfaceText,
        $matches
    );

    if ($matched !== 1) {
        return null;
    }

    return [
        'honorific' => prose_character_normalized_key((string)$matches[1]),
        'surname' => prose_character_normalized_key((string)$matches[2]),
    ];
}

function prose_character_normalize_surface_forms(
    string $surfaceForm
): array {

    $surfaceForm = trim($surfaceForm);

    $normalized = [
        $surfaceForm,
    ];

    $stripped = preg_replace(
        '/^(mrs\.?|ms\.?|miss|mr\.?|sir|lady|lord|dr\.?)\s+/i',
        '',
        $surfaceForm
    );

    if (is_string($stripped) && trim($stripped) !== '') {
        $normalized[] = trim($stripped);
    }

    $honorificExpanded = preg_replace(
        '/^(mrs|ms|mr|dr)\s+/i',
        '$1. ',
        $surfaceForm
    );

    if (is_string($honorificExpanded) && trim($honorificExpanded) !== '') {
        $normalized[] = trim($honorificExpanded);
    }

    $honorificCompacted = preg_replace(
        '/^(mrs|ms|mr|dr)\.\s+/i',
        '$1 ',
        $surfaceForm
    );

    if (is_string($honorificCompacted) && trim($honorificCompacted) !== '') {
        $normalized[] = trim($honorificCompacted);
    }

    return array_values(array_unique($normalized));
}

function prose_character_surface_forms_from_label(string $label): array
{
    $label = trim($label);

    if ($label === '') {
        return [];
    }

    $surfaceForms = prose_character_normalize_surface_forms($label);

    $stripped = preg_replace(
        '/^(mrs\.?|ms\.?|miss|mr\.?|sir|lady|lord|dr\.?)\s+/i',
        '',
        $label
    );

    if (is_string($stripped) && trim($stripped) !== '') {
        foreach (prose_character_normalize_surface_forms(trim($stripped)) as $surfaceForm) {
            $surfaceForms[] = $surfaceForm;
        }
    }

    $parts = preg_split('/\s+/', $label);

    if (is_array($parts) && count($parts) >= 2) {
        $last = trim((string)end($parts));

        if ($last !== '') {
            $surfaceForms[] = $last;
        }
    }

    return array_values(array_unique(array_filter(
        $surfaceForms,
        static fn (string $surfaceForm): bool => trim($surfaceForm) !== ''
    )));
}

function prose_character_dynamic_surface_rules(PDO $pdo): array
{
    $sql = "
        SELECT
            e.id AS entity_id,
            COALESCE(
                NULLIF(c.char_name_full, ''),
                NULLIF(et.canonical_label, ''),
                e.id
            ) AS candidate_label,
            c.char_name_full,
            c.char_name_first,
            c.char_name_last,
            et.canonical_label,
            el.label AS entity_label,
            sa.alias AS semantic_alias
        FROM entities e
        LEFT JOIN characters c
            ON c.entity_id = e.id
        LEFT JOIN entity_texts et
            ON et.entity_id = e.id
        LEFT JOIN entity_labels el
            ON el.entity_id = e.id
        LEFT JOIN semantic_aliases sa
            ON sa.entity_id = e.id
        WHERE e.entity_type_id = 'entity_type_character'
        ORDER BY e.id ASC
    ";

    $statement = $pdo->query($sql);

    if (!$statement instanceof PDOStatement) {
        return [];
    }

    $rulesByEntity = [];

    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        if (!is_array($row)) {
            continue;
        }

        $entityId = trim((string)($row['entity_id'] ?? ''));

        if ($entityId === '') {
            continue;
        }

        if (!isset($rulesByEntity[$entityId])) {
            $rulesByEntity[$entityId] = [
                'surface_forms' => [],
                'confidence' => 0.95,
                'resolved_entity_id' => $entityId,
                'candidate_label' => trim((string)($row['candidate_label'] ?? $entityId)),
            ];
        }

        foreach ([
            'char_name_full',
            'char_name_first',
            'char_name_last',
            'canonical_label',
            'entity_label',
            'semantic_alias',
        ] as $field) {
            $value = trim((string)($row[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            foreach (prose_character_surface_forms_from_label($value) as $surfaceForm) {
                $rulesByEntity[$entityId]['surface_forms'][] = $surfaceForm;
            }
        }
    }

    $rules = [];

    foreach ($rulesByEntity as $rule) {
        $surfaceForms = array_values(array_unique($rule['surface_forms']));

        if ($surfaceForms === []) {
            continue;
        }

        $rule['surface_forms'] = $surfaceForms;
        $rules[] = $rule;
    }

    return $rules;
}

function prose_character_surface_rules(PDO $pdo): array
{
    $rules = prose_character_known_surface_forms();

    foreach (prose_character_dynamic_surface_rules($pdo) as $dynamicRule) {
        $rules[] = $dynamicRule;
    }

    return $rules;
}

function prose_character_candidate_index(PDO $pdo): array
{
    $sql = "
        SELECT
            e.id AS resolved_entity_id,
            e.entity_type_id AS resolved_entity_type_id,
            COALESCE(
                NULLIF(c.char_name_full, ''),
                NULLIF(et.canonical_label, ''),
                e.id
            ) AS candidate_label,
            c.search_name,
            c.char_name_full,
            c.char_name_first,
            c.char_name_last,
            et.canonical_label,
            el.label AS entity_label,
            sa.alias AS semantic_alias
        FROM entities e
        LEFT JOIN characters c
            ON c.entity_id = e.id
        LEFT JOIN entity_labels el
            ON el.entity_id = e.id
        LEFT JOIN semantic_aliases sa
            ON sa.entity_id = e.id
        LEFT JOIN entity_texts et
            ON et.entity_id = e.id
        WHERE e.entity_type_id = 'entity_type_character'
        ORDER BY e.id ASC
    ";

    $statement = $pdo->query($sql);

    if (!$statement instanceof PDOStatement) {
        return [];
    }

    $rows = [];

    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        if (!is_array($row)) {
            continue;
        }

        $rows[] = $row;
    }

    return $rows;
}

function prose_character_add_resolution_candidate(
    array &$candidatesByEntity,
    array $row,
    string $methodClassvalId,
    float $score,
    string $notes
): void {

    $entityId = trim((string)($row['resolved_entity_id'] ?? ''));

    if ($entityId === '') {
        return;
    }

    $candidate = [
        'candidate_label' => (string)($row['candidate_label'] ?? $entityId),
        'resolved_entity_id' => $entityId,
        'resolved_entity_type_id' => (string)($row['resolved_entity_type_id'] ?? 'entity_type_character'),
        'resolution_method_classval_id' => $methodClassvalId,
        'candidate_score' => $score,
        'scoring_notes' => $notes,
    ];

    if (
        !isset($candidatesByEntity[$entityId])
        || (float)$candidatesByEntity[$entityId]['candidate_score'] < $score
    ) {
        $candidatesByEntity[$entityId] = $candidate;
        return;
    }

    if ((float)$candidatesByEntity[$entityId]['candidate_score'] === $score) {
        $candidatesByEntity[$entityId]['scoring_notes'] .= '; ' . $notes;
    }
}

function generateResolutionCandidates(
    PDO $pdo,
    string $surfaceText
): array {

    $surfaceText = trim($surfaceText);

    if ($surfaceText === '') {
        return [];
    }

    $surfaceKey = prose_character_normalized_key($surfaceText);
    $honorificSurname = prose_character_extract_honorific_surname($surfaceText);
    $surfaceTokens = array_values(array_filter(
        preg_split('/\s+/u', $surfaceKey) ?: [],
        static fn (string $token): bool => $token !== ''
    ));

    $rows = prose_character_candidate_index($pdo);
    $candidatesByEntity = [];

    /* 1. exact canonical label */
    foreach ($rows as $row) {
        $canonicalLabel = trim((string)($row['canonical_label'] ?? ''));

        if ($canonicalLabel !== '' && strcasecmp($canonicalLabel, $surfaceText) === 0) {
            prose_character_add_resolution_candidate(
                $candidatesByEntity,
                $row,
                'RESOLUTION_METHOD_CANONICAL_LABEL',
                0.99,
                'Matched exact canonical label "' . $canonicalLabel . '"'
            );
        }
    }

    /* 2. exact semantic alias */
    foreach ($rows as $row) {
        $semanticAlias = trim((string)($row['semantic_alias'] ?? ''));

        if ($semanticAlias !== '' && strcasecmp($semanticAlias, $surfaceText) === 0) {
            prose_character_add_resolution_candidate(
                $candidatesByEntity,
                $row,
                'RESOLUTION_METHOD_SEMANTIC_ALIAS',
                0.97,
                'Matched exact semantic alias "' . $semanticAlias . '"'
            );
        }
    }

    /* 3. normalized semantic alias */
    foreach ($rows as $row) {
        $semanticAlias = trim((string)($row['semantic_alias'] ?? ''));

        if ($semanticAlias !== '' && prose_character_normalized_key($semanticAlias) === $surfaceKey) {
            prose_character_add_resolution_candidate(
                $candidatesByEntity,
                $row,
                'RESOLUTION_METHOD_NORMALIZED_SEMANTIC_ALIAS',
                0.94,
                'Matched normalized semantic alias "' . prose_character_normalized_key($semanticAlias) . '"'
            );
        }
    }

    /* 4. honorific + surname */
    if ($honorificSurname !== null) {
        foreach ($rows as $row) {
            $lastName = prose_character_normalized_key((string)($row['char_name_last'] ?? ''));

            if ($lastName !== '' && $lastName === $honorificSurname['surname']) {
                prose_character_add_resolution_candidate(
                    $candidatesByEntity,
                    $row,
                    'RESOLUTION_METHOD_HONORIFIC_SURNAME',
                    0.90,
                    'Matched canonical honorific+surname alias "' . $surfaceKey . '"'
                );
            }
        }
    }

    /* 5. surname alias */
    foreach ($rows as $row) {
        $lastName = prose_character_normalized_key((string)($row['char_name_last'] ?? ''));

        if ($lastName !== '' && $lastName === $surfaceKey) {
            prose_character_add_resolution_candidate(
                $candidatesByEntity,
                $row,
                'RESOLUTION_METHOD_SURNAME_ALIAS',
                0.76,
                'Matched surname alias "' . $surfaceKey . '"'
            );
        }
    }

    /* 6. token decomposition */
    if (count($surfaceTokens) > 1) {
        foreach ($rows as $row) {
            $rowTokens = [
                prose_character_normalized_key((string)($row['char_name_first'] ?? '')),
                prose_character_normalized_key((string)($row['char_name_last'] ?? '')),
            ];

            $rowTokens = array_values(array_filter($rowTokens));

            if ($rowTokens !== [] && count(array_intersect($surfaceTokens, $rowTokens)) === count($rowTokens)) {
                prose_character_add_resolution_candidate(
                    $candidatesByEntity,
                    $row,
                    'RESOLUTION_METHOD_TOKEN_DECOMPOSITION',
                    0.62,
                    'Matched token decomposition from surface "' . $surfaceKey . '"'
                );
            }
        }
    }

    /* 7. generated lexical heuristics: fallback only */
    if ($candidatesByEntity === []) {
        $generatedTokens = $honorificSurname !== null
            ? [$honorificSurname['surname']]
            : $surfaceTokens;

        foreach ($rows as $row) {
            $lastName = prose_character_normalized_key((string)($row['char_name_last'] ?? ''));

            if ($lastName !== '' && in_array($lastName, $generatedTokens, true)) {
                prose_character_add_resolution_candidate(
                    $candidatesByEntity,
                    $row,
                    'RESOLUTION_METHOD_GENERATED_LEXICAL_FALLBACK',
                    0.45,
                    'Generated lexical fallback from surname token "' . $lastName . '"'
                );
            }
        }
    }

    $candidates = array_values($candidatesByEntity);

    usort(
        $candidates,
        static function (array $a, array $b): int {
            return ((float)$b['candidate_score']) <=> ((float)$a['candidate_score']);
        }
    );

    $topScore = $candidates[0]['candidate_score'] ?? null;

    if ($topScore !== null) {
        $topCount = count(array_filter(
            $candidates,
            static fn (array $candidate): bool => (float)$candidate['candidate_score'] === (float)$topScore
        ));

        if ($topCount > 1) {
            foreach ($candidates as &$candidate) {
                if ((float)$candidate['candidate_score'] === (float)$topScore) {
                    $candidate['scoring_notes'] .= '; Rejected exact winner due to ambiguity count=' . $topCount;
                }
            }
            unset($candidate);
        }
    }

    return $candidates;
}

function selectBestCandidate(array $candidates): ?array
{
    if ($candidates === []) {
        return null;
    }

    $topScore = (float)($candidates[0]['candidate_score'] ?? 0.0);
    $topCandidates = array_values(array_filter(
        $candidates,
        static fn (array $candidate): bool => (float)($candidate['candidate_score'] ?? 0.0) === $topScore
    ));

    if (count($topCandidates) !== 1) {
        return null;
    }

    return $topCandidates[0];
}

function resolve_character_surface_form(
    PDO $pdo,
    string $surfaceForm
): ?array {

    return selectBestCandidate(
        generateResolutionCandidates($pdo, $surfaceForm)
    );
}

function suggest_prose_characters(
    PDO $pdo,
    string $proseBody,
    array $context = []
): array {

    $suggestions = [];
    $seenEntityIds = [];

    foreach (prose_character_surface_rules($pdo) as $characterRule) {
        $matchedEvidence = [];
        $matchedOffsets = [];

        foreach ($characterRule['surface_forms'] as $surfaceForm) {
            $offsets = prose_character_find_offsets($proseBody, $surfaceForm);

            if ($offsets === []) {
                continue;
            }

            $matchedEvidence[] = [
                'type' => 'exact_name_match',
                'text' => $surfaceForm,
            ];

            foreach ($offsets as $offset) {
                $matchedOffsets[] = $offset;
            }
        }

        if ($matchedEvidence === []) {
            continue;
        }

        $primarySurfaceForm = (string)($matchedEvidence[0]['text'] ?? $characterRule['surface_forms'][0]);
        $candidates = generateResolutionCandidates($pdo, $primarySurfaceForm);
        $resolution = selectBestCandidate($candidates);

        if ($resolution === null && isset($characterRule['resolved_entity_id'])) {
            $resolution = [
                'candidate_label' => (string)($characterRule['candidate_label'] ?? $primarySurfaceForm),
                'resolved_entity_id' => (string)$characterRule['resolved_entity_id'],
                'resolved_entity_type_id' => 'entity_type_character',
                'resolution_method_classval_id' => 'RESOLUTION_METHOD_STATIC_SURFACE_RULE',
                'candidate_score' => (float)($characterRule['confidence'] ?? 0.50),
                'scoring_notes' => 'Resolved through static or generated surface rule after candidate generation returned no unique winner',
            ];
        }

        $resolvedEntityId = $resolution['resolved_entity_id'] ?? null;

        if (is_string($resolvedEntityId) && $resolvedEntityId !== '') {
            if (isset($seenEntityIds[$resolvedEntityId])) {
                continue;
            }

            $seenEntityIds[$resolvedEntityId] = true;
        }

        $suggestions[] = [
            'suggestion_type' => 'character',
            'surface_forms' => $characterRule['surface_forms'],
            'matched_surface_form' => $primarySurfaceForm,
            'candidate_label' => $resolution['candidate_label'] ?? null,
            'resolved_entity_id' => $resolution['resolved_entity_id'] ?? null,
            'resolved_entity_type_id' => $resolution['resolved_entity_type_id'] ?? null,
            'resolution_method_classval_id' => $resolution['resolution_method_classval_id'] ?? null,
            'resolution_status' => $resolution === null
                ? 'unresolved'
                : 'resolved',
            'confidence' => $resolution['candidate_score'] ?? (float)$characterRule['confidence'],
            'candidate_score' => $resolution['candidate_score'] ?? null,
            'scoring_notes' => $resolution['scoring_notes'] ?? 'No unique resolution candidate selected',
            'resolution_candidates' => $candidates,
            'evidence' => $matchedEvidence,
            'offsets' => $matchedOffsets,
            'status' => $resolution === null
                ? 'suggested_unresolved_entity'
                : 'suggested',
        ];
    }

    return [
        'suggestions' => [
            'characters' => $suggestions,
        ],
        'suggestion_mode' => 'deterministic_ordered_resolution_candidates',
        'mutates_canonical_ontology' => false,
        'requires_apply_boundary' => true,
        'doctrine' => [
            'Suggestions are advisory.',
            'Suggestions are reversible.',
            'Suggestions require explicit evidence.',
            'Candidate generation is evidence production, not ontology adjudication.',
            'Canonical identity authority is entities.id.',
            'No symbolic identifiers are synthesized from prose.',
            'Generated lexical decomposition is fallback-only behaviour.',
            'No persistence occurs in this workflow.',
        ],
    ];
}
