<?php

declare(strict_types=1);

require_once __DIR__ . '/prose_metadata_deriver.php';

/**
 * Semantic primitive derivation.
 *
 * Doctrine:
 * - evidence-backed only
 * - deterministic only
 * - suggestion-only
 * - no ontology mutation
 * - no canonical persistence
 * - author approval required before reuse
 *
 * Pipeline:
 * prose
 * -> evidence signals
 * -> semantic primitive suggestions
 * -> author approval
 * -> reusable semantic derivation rules
 */

function derive_semantic_primitives(
    string $proseBody,
    array $context = []
): array {

    $normalized = prose_normalized_body($proseBody);

    if ($normalized === '') {
        return [
            'derivation_mode' => 'empty',
            'signals' => [],
            'primitive_suggestions' => [],
        ];
    }

    $signals = [];
    $primitiveSuggestions = [];

    $signalMap = [

        'threshold_crossing' => [
            'Royal Ballroom Dance Society',
            'four doors',
            'doors swing silently inward',
            'step forward',
            'doors close',
            'threshold',
            'entering',
        ],

        'institutional_admission' => [
            'sign in at the desk',
            'we have been expecting you',
            'appointment',
            'expecting you',
            'authorized',
        ],

        'reception_authority' => [
            'Mrs Higgins',
            'ledger',
            'reception desk',
            'gatekeeper',
            'audit of my appearance',
        ],

        'role_self_identification' => [
            'I’m Shay Vertue',
            'movement analyst',
            'Follow #8',
            'Miss Vertue',
        ],

        'authorised_passage' => [
            'Ms Kingsley is expecting you',
            'last door on the right',
            'passage authorized',
            'assigned me',
        ],

        'summons_to_authority' => [
            'Ms Kingsley',
            'office at the end of the hall',
            'appointment with Ms Kingsley',
        ],

        'guided_transition' => [
            'led from',
            'intercepted by',
            'taken down',
            'guided',
        ],

        'descent_into_operational_space' => [
            'down into',
            'training-room level',
            'training level',
            'baseline testing',
        ],
    ];

    foreach ($signalMap as $primitive => $needles) {

        $matched = [];

        foreach ($needles as $needle) {
            if (mb_stripos($normalized, $needle) !== false) {
                $matched[] = $needle;
            }
        }

        if ($matched === []) {
            continue;
        }

        $signals[$primitive] = $matched;

        $primitiveSuggestions[] = [
            'primitive' => $primitive,
            'confidence' => 'evidence_detected',
            'matched_signals' => $matched,
            'approved' => false,
        ];
    }

    return [
        'derivation_mode' => 'semantic_primitive_suggestion',
        'signals' => $signals,
        'primitive_suggestions' => $primitiveSuggestions,
        'approval_required' => true,
        'persistence_allowed' => false,
    ];
}
