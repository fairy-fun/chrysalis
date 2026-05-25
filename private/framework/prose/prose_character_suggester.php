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

function resolve_character_surface_form(
    PDO $pdo,
    string $surfaceForm
): ?array {

    $sql = "
        SELECT
            e.id AS resolved_entity_id,
            e.entity_type_id AS resolved_entity_type_id,
            COALESCE(
                NULLIF(c.char_name_full, ''),
                NULLIF(et.canonical_label, ''),
                e.id
            ) AS candidate_label
        FROM entities e
        LEFT JOIN characters c
            ON c.entity_id = e.id
        LEFT JOIN entity_labels el
            ON el.entity_id = e.id
        LEFT JOIN semantic_aliases sa
            ON sa.entity_id = e.id
        LEFT JOIN entity_texts et
            ON et.entity_id = e.id
        WHERE
            e.entity_type_id = 'entity_type_character'
            AND (
                LOWER(c.search_name) = LOWER(?)
                OR LOWER(c.char_name_full) = LOWER(?)
                OR LOWER(c.char_name_first) = LOWER(?)
                OR LOWER(c.char_name_last) = LOWER(?)
                OR LOWER(el.label) = LOWER(?)
                OR LOWER(sa.alias) = LOWER(?)
                OR LOWER(et.canonical_label) = LOWER(?)
            )
        LIMIT 1
    ";

    $statement = $pdo->prepare($sql);

    foreach (prose_character_normalize_surface_forms($surfaceForm) as $candidateSurface) {
        $statement->execute([
            $candidateSurface,
            $candidateSurface,
            $candidateSurface,
            $candidateSurface,
            $candidateSurface,
            $candidateSurface,
            $candidateSurface,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            return [
                'candidate_label' => (string)$row['candidate_label'],
                'resolved_entity_id' => (string)$row['resolved_entity_id'],
                'resolved_entity_type_id' => (string)$row['resolved_entity_type_id'],
            ];
        }
    }

    return null;
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

        $primarySurfaceForm = (string)$characterRule['surface_forms'][0];

        $resolution = null;

        if (isset($characterRule['resolved_entity_id'])) {
            $resolution = [
                'candidate_label' => (string)($characterRule['candidate_label'] ?? $primarySurfaceForm),
                'resolved_entity_id' => (string)$characterRule['resolved_entity_id'],
                'resolved_entity_type_id' => 'entity_type_character',
            ];
        } else {
            $resolution = resolve_character_surface_form(
                $pdo,
                $primarySurfaceForm
            );
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
            'candidate_label' => $resolution['candidate_label'] ?? null,
            'resolved_entity_id' => $resolution['resolved_entity_id'] ?? null,
            'resolved_entity_type_id' => $resolution['resolved_entity_type_id'] ?? null,
            'resolution_status' => $resolution === null
                ? 'unresolved'
                : 'resolved',
            'confidence' => (float)$characterRule['confidence'],
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
        'suggestion_mode' => 'deterministic_exact_surface_forms',
        'mutates_canonical_ontology' => false,
        'requires_apply_boundary' => true,
        'doctrine' => [
            'Suggestions are advisory.',
            'Suggestions are reversible.',
            'Suggestions require explicit evidence.',
            'Canonical identity authority is entities.id.',
            'No symbolic identifiers are synthesized from prose.',
            'No persistence occurs in this workflow.',
        ],
    ];
}
