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

    $offsets = [];
    $cursor = 0;

    while (true) {
        $position = mb_stripos($proseBody, $surfaceForm, $cursor);

        if ($position === false) {
            break;
        }

        $offsets[] = [
            'start' => $position,
            'end' => $position + mb_strlen($surfaceForm),
        ];

        $cursor = $position + mb_strlen($surfaceForm);
    }

    return $offsets;
}

function prose_character_normalize_surface_forms(
    string $surfaceForm
): array {

    $normalized = [
        trim($surfaceForm),
    ];

    $stripped = preg_replace(
        '/^(mrs\.?|ms\.?|miss|mr\.?)\s+/i',
        '',
        $surfaceForm
    );

    if (is_string($stripped) && trim($stripped) !== '') {
        $normalized[] = trim($stripped);
    }

    return array_values(array_unique($normalized));
}

function resolve_character_surface_form(
    PDO $pdo,
    string $surfaceForm
): ?array {

    $sql = "
        SELECT
            e.id AS resolved_entity_id,
            e.entity_type_id AS resolved_entity_type_id,
            c.char_name_full AS candidate_label
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

    foreach (prose_character_known_surface_forms() as $characterRule) {
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

        $resolution = resolve_character_surface_form(
            $pdo,
            $primarySurfaceForm
        );

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
