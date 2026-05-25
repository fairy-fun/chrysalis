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

/* remainder of file unchanged until suggest_prose_characters */
