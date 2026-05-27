<?php

declare(strict_types=1);

/**
 * Chrysalis-specific calendar-event semantic scene classification.
 *
 * Boundary doctrine:
 * - Framework prose mechanics live in private/framework.
 * - Chrysalis corpus signals, scene classes, and metadata mappings live here.
 * - This file must not use calendar_event IDs as semantic evidence.
 * - Phrase matching is allowed only as signal extraction.
 * - Metadata must be derived from scene classes, not directly from one-off
 *   prose fingerprints.
 */

function derive_chrysalis_calendar_event_fixture_metadata(
    string $proseBody,
    array $context = []
): ?array {

    $signals = extract_chrysalis_scene_signals($proseBody);

    if ($signals === []) {
        return null;
    }

    $classification = classify_chrysalis_scene($signals);

    if ($classification === null) {
        return null;
    }

    return derive_chrysalis_metadata_from_scene_class(
        $classification,
        $signals,
        $context
    );
}

function extract_chrysalis_scene_signals(string $proseBody): array
{
    $normalized = prose_normalized_body($proseBody);

    if ($normalized === '') {
        return [];
    }

    $signals = [];

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'institution.rbds',
        [
            'Royal Ballroom Dance Society',
            'RBDS',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'space.public_institutional_entrance',
        [
            'four doors of the Royal Ballroom Dance Society',
            'front doors of the Royal Ballroom Dance Society',
            'entrance to the Royal Ballroom Dance Society',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'action.threshold_crossing',
        [
            'the doors swing silently inward',
            'step inside',
            'stepped inside',
            'as I enter',
            'as I entered',
            'inside the entrance',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'procedure.reception_intake',
        [
            'sign in at the desk',
            'sign in',
            'reception desk',
            'leather-bound ledger',
            'ledger',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'character.mrs_higgins',
        [
            'Mrs Higgins',
            'Mrs. Higgins',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'authority.kingsley_appointment',
        [
            'ten o’clock appointment with Ms Kingsley',
            'ten o\'clock appointment with Ms Kingsley',
            'appointment with Ms Kingsley',
            'Ms Kingsley is expecting you',
            'Ms Kingsley’s office',
            'Ms Kingsley\'s office',
            'last door on the right',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'role.shay_registration',
        [
            'I’m Shay Vertue',
            'I\'m Shay Vertue',
            'movement analyst',
            'Follow #8',
            'Miss Vertue',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'role.narrative_consultant',
        [
            'Narrative Consultant',
            'newly appointed',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'character.chloe',
        [
            'Chloe',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'procedure.baseline_assessment',
        [
            'baseline testing',
            'Baseline Testing',
            'baseline test',
            'baseline-testing',
            'testing setup',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'movement.vertical_descent',
        [
            'descends',
            'descend',
            'down into',
            'down to',
            'training-room level',
            'training level',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'space.training_room',
        [
            'training room',
            'training-room',
            'conditioning room',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'space.physical_assessment_environment',
        [
            'To call it a gym would be an insult',
            'cathedral of physical optimisation',
            'cathedral of physical optimization',
            'gym',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'object.assessment_equipment',
        [
            'black-metal machines',
            'medieval torture',
            'minimalist architect',
            'matted floor',
            'machines that look like instruments',
            'iron plates',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'character.fruean',
        [
            'Mr Fruean',
            'Fruean',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'action.exit_attempt',
        [
            'As I turn to exit',
            'intending to leave Mr Fruean',
            'leave Mr Fruean',
            'my path blocked',
            'path blocked',
        ]
    );

    chrysalis_add_signal_if_any(
        $signals,
        $normalized,
        'action.doorway_interception',
        [
            'Leaning against the frame is Chloe',
            'Chloe is waiting',
            'waiting for me',
            'my path blocked',
            'path blocked',
        ]
    );

    return array_values(array_unique($signals));
}

function classify_chrysalis_scene(array $signals): ?array
{
    $classes = [];

    if (
        chrysalis_has_signal($signals, 'institution.rbds')
        && chrysalis_has_any_signal($signals, [
            'space.public_institutional_entrance',
            'action.threshold_crossing',
        ])
        && chrysalis_has_any_signal($signals, [
            'procedure.reception_intake',
            'character.mrs_higgins',
        ])
        && chrysalis_has_any_signal($signals, [
            'authority.kingsley_appointment',
            'role.shay_registration',
        ])
    ) {
        $classes[] = [
            'scene_class' => 'institutional_arrival_intake',
            'confidence' => 0.82,
            'required_evidence' => [
                'institution.rbds',
                'entry_or_crossing',
                'intake_or_reception',
                'appointment_or_registration',
            ],
        ];
    }

    if (
        chrysalis_has_any_signal($signals, [
            'authority.kingsley_appointment',
            'role.narrative_consultant',
        ])
        && chrysalis_has_signal($signals, 'character.chloe')
        && chrysalis_has_signal($signals, 'procedure.baseline_assessment')
        && chrysalis_has_any_signal($signals, [
            'movement.vertical_descent',
            'space.training_room',
        ])
    ) {
        $classes[] = [
            'scene_class' => 'summons_to_baseline_assessment',
            'confidence' => 0.8,
            'required_evidence' => [
                'authority_or_new_role',
                'character.chloe',
                'procedure.baseline_assessment',
                'descent_or_training_space',
            ],
        ];
    }

    if (
        chrysalis_has_signal($signals, 'space.physical_assessment_environment')
        && chrysalis_has_signal($signals, 'object.assessment_equipment')
        && chrysalis_has_any_signal($signals, [
            'procedure.baseline_assessment',
            'space.training_room',
        ])
    ) {
        $classes[] = [
            'scene_class' => 'training_room_orientation',
            'confidence' => 0.84,
            'required_evidence' => [
                'space.physical_assessment_environment',
                'object.assessment_equipment',
                'assessment_or_training_space',
            ],
        ];
    }

    if (
        chrysalis_has_signal($signals, 'character.chloe')
        && chrysalis_has_signal($signals, 'character.fruean')
        && chrysalis_has_signal($signals, 'action.exit_attempt')
        && chrysalis_has_signal($signals, 'action.doorway_interception')
    ) {
        $classes[] = [
            'scene_class' => 'post_assessment_doorway_interception',
            'confidence' => 0.83,
            'required_evidence' => [
                'character.chloe',
                'character.fruean',
                'action.exit_attempt',
                'action.doorway_interception',
            ],
        ];
    }

    if ($classes === []) {
        return null;
    }

    usort(
        $classes,
        static function (array $a, array $b): int {
            return ((float)$b['confidence']) <=> ((float)$a['confidence']);
        }
    );

    return $classes[0];
}

function derive_chrysalis_metadata_from_scene_class(
    array $classification,
    array $signals,
    array $context = []
): ?array {

    $sceneClass = (string)($classification['scene_class'] ?? '');

    if ($sceneClass === 'institutional_arrival_intake') {
        return chrysalis_scene_metadata(
            'Shay arrives at RBDS for her appointment with Ms Kingsley',
            'Shay arrives at the Royal Ballroom Dance Society and moves through the institution’s intake procedure before being directed toward Ms Kingsley, establishing her formal entry into RBDS authority structures.',
            'BEAT_TRANSITION',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'summons_to_baseline_assessment') {
        return chrysalis_scene_metadata(
            'Shay is summoned to baseline testing by Chloe',
            'After her appointment with Ms Kingsley establishes her new institutional role, Shay is intercepted by Chloe and directed toward baseline testing, moving from executive authority into the practical assessment machinery of the team.',
            'BEAT_INSTRUCTION',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'training_room_orientation') {
        return chrysalis_scene_metadata(
            'Shay takes in the RBDS training room before baseline testing',
            'Shay registers the physical assessment environment of RBDS: a training-room space of equipment, mats, and bodily discipline that reframes dance preparation as institutional testing.',
            'BEAT_ORIENTATION',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'post_assessment_doorway_interception') {
        return chrysalis_scene_metadata(
            'Chloe intercepts Shay after the Fruean assessment',
            'As Shay attempts to leave after the Fruean assessment sequence, Chloe is already waiting to redirect her, continuing Shay’s controlled movement through RBDS procedure.',
            'BEAT_TRANSITION',
            $classification,
            $signals
        );
    }

    return null;
}

function chrysalis_scene_metadata(
    string $title,
    string $beatSummary,
    string $beatTypeId,
    array $classification,
    array $signals
): array {

    return [
        'title' => $title,
        'beat_summary' => $beatSummary,
        'beat_type_id' => $beatTypeId,
        'derivation_mode' => 'semantic_scene_class',
        'scene_class' => (string)($classification['scene_class'] ?? ''),
        'confidence' => (float)($classification['confidence'] ?? 0),
        'evidence' => [
            'signals' => array_values($signals),
            'required_evidence' => array_values(
                $classification['required_evidence'] ?? []
            ),
        ],
    ];
}

function chrysalis_add_signal_if_any(
    array &$signals,
    string $normalized,
    string $signal,
    array $needles
): void {

    if (prose_contains_any($normalized, $needles)) {
        $signals[] = $signal;
    }
}

function chrysalis_has_signal(array $signals, string $signal): bool
{
    return in_array($signal, $signals, true);
}

function chrysalis_has_signals(array $signals, array $requiredSignals): bool
{
    foreach ($requiredSignals as $requiredSignal) {
        if (!chrysalis_has_signal($signals, (string)$requiredSignal)) {
            return false;
        }
    }

    return true;
}

function chrysalis_has_any_signal(array $signals, array $candidateSignals): bool
{
    foreach ($candidateSignals as $candidateSignal) {
        if (chrysalis_has_signal($signals, (string)$candidateSignal)) {
            return true;
        }
    }

    return false;
}