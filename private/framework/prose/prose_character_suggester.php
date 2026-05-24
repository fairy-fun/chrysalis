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
            'entity_id' => 'character:shay_vertue',
            'surface_forms' => [
                'Shay',
            ],
            'confidence' => 0.99,
        ],
        [
            'entity_id' => null,
            'surface_forms' => [
                'Chloe',
            ],
            'confidence' => 0.95,
        ],
        [
            'entity_id' => null,
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

function suggest_prose_characters(
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

        $entityId = $characterRule['entity_id'];

        $suggestions[] = [
            'suggestion_type' => 'character',
            'entity_id' => $entityId,
            'surface_forms' => $characterRule['surface_forms'],
            'confidence' => (float)$characterRule['confidence'],
            'evidence' => $matchedEvidence,
            'offsets' => $matchedOffsets,
            'status' => $entityId === null
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
            'No persistence occurs in this workflow.',
        ],
    ];
}
