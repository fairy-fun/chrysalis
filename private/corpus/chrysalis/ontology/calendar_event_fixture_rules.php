<?php

declare(strict_types=1);

/**
 * Chrysalis-specific calendar-event fixture metadata rules.
 *
 * This file is intentionally outside private/framework. The framework may
 * provide prose mechanics and orchestration hooks, but corpus fingerprints,
 * character names, institutional references, and scene signatures belong here.
 *
 * Transitional rule:
 * These fixtures still use concrete prose fingerprints. They must not use
 * calendar event IDs as semantic evidence, because event identity is temporal
 * identity only and must not force ontology derivation.
 */
function derive_chrysalis_calendar_event_fixture_metadata(
    string $proseBody,
    array $context = []
): ?array {

    $normalized = prose_normalized_body($proseBody);

    if ($normalized === '') {
        return null;
    }

    $hasRbdsThreshold = prose_contains_any($normalized, [
        'four doors of the Royal Ballroom Dance Society',
        'Royal Ballroom Dance Society',
        'dark, heavy wood',
        'brass handles',
        'the doors swing silently inward',
        'sign in at the desk',
    ]);

    $hasMrsHiggins = prose_contains_any($normalized, [
        'Mrs Higgins',
        'Mrs. Higgins',
        'reception desk',
        'leather-bound ledger',
        'Welcome to the zoo',
    ]);

    $hasKingsleyAppointment = prose_contains_any($normalized, [
        'ten o’clock appointment with Ms Kingsley',
        'ten o\'clock appointment with Ms Kingsley',
        'appointment with Ms Kingsley',
        'Ms Kingsley is expecting you',
        'Ms Kingsley’s office',
        'Ms Kingsley\'s office',
        'last door on the right',
    ]);

    $hasShayRoleSignal = prose_contains_any($normalized, [
        'I’m Shay Vertue',
        'I\'m Shay Vertue',
        'movement analyst',
        'Follow #8',
        'Miss Vertue',
    ]);

    $isRbdsArrivalFixture = $hasRbdsThreshold
        && $hasMrsHiggins
        && $hasKingsleyAppointment
        && $hasShayRoleSignal;

    if ($isRbdsArrivalFixture) {
        return [
            'title' => 'Shay arrives at RBDS for her appointment with Ms Kingsley',
            'beat_summary' => 'Shay arrives at the Royal Ballroom Dance Society, is admitted through its intimidating front doors by a doorman, and signs in with Mrs Higgins, who registers her as the new movement analyst and Follow #8 before sending her down the founder-lined corridor to Ms Kingsley’s office.',
            'beat_type_id' => 'BEAT_TRANSITION',
            'derivation_mode' => 'semantic_rule',
            'evidence' => array_values(array_filter([
                $hasRbdsThreshold ? 'RBDS_threshold' : null,
                $hasMrsHiggins ? 'Mrs_Higgins_reception' : null,
                $hasKingsleyAppointment ? 'Kingsley_appointment' : null,
                $hasShayRoleSignal ? 'Shay_role_signal' : null,
            ])),
        ];
    }

    $hasInstitutionalSetup = prose_contains_any($normalized, [
        'Narrative Consultant',
        'Kingsley',
        'Ms Kingsley',
        'Kingsley’s office',
        'Kingsley\'s office',
    ]);

    $hasChloe = prose_contains_any($normalized, [
        'Chloe',
    ]);

    $hasBaselineTesting = prose_contains_any($normalized, [
        'baseline testing',
        'Baseline Testing',
        'baseline test',
        'baseline-testing',
        'testing setup',
        'training level',
        'training-room level',
        'training room',
    ]);

    $hasDescent = prose_contains_any($normalized, [
        'descends',
        'descend',
        'down into',
        'down to',
        'training level',
        'training-room level',
        'training room',
    ]);

    $isBaselineTestingFixture = $hasInstitutionalSetup
        && $hasChloe
        && $hasBaselineTesting
        && $hasDescent;

    if ($isBaselineTestingFixture) {
        return [
            'title' => 'Shay is summoned to baseline testing by Chloe',
            'beat_summary' => 'After leaving Ms Kingsley’s office newly appointed as “Narrative Consultant,” Shay is intercepted by Chloe, a coolly indifferent black-clad team member, and led from RBDS’s grand executive corridor down into the functional training-room level for baseline testing, marking her first descent from institutional performance theatre into the physical machinery of the team.',
            'beat_type_id' => 'BEAT_INSTRUCTION',
            'derivation_mode' => 'semantic_rule',
            'evidence' => array_values(array_filter([
                $hasInstitutionalSetup ? 'institutional_setup' : null,
                $hasChloe ? 'Chloe' : null,
                $hasBaselineTesting ? 'baseline_testing' : null,
                $hasDescent ? 'descent_to_training_level' : null,
            ])),
        ];
    }

    $hasGymCathedral = prose_contains_any($normalized, [
        'To call it a gym would be an insult',
        'cathedral of physical optimisation',
        'cathedral of physical optimization',
    ]);

    $hasTrainingMachines = prose_contains_any($normalized, [
        'black-metal machines',
        'medieval torture',
        'minimalist architect',
        'matted floor',
        'machines that look like instruments',
    ]);

    $hasPhysicalTestingContext = prose_contains_any($normalized, [
        'baseline testing',
        'baseline test',
        'testing setup',
        'physical optimisation',
        'physical optimization',
        'training room',
        'training-room',
        'gym',
    ]);

    $isTrainingRoomFixture = $hasGymCathedral
        && $hasTrainingMachines
        && $hasPhysicalTestingContext;

    if ($isTrainingRoomFixture) {
        return [
            'title' => 'Shay takes in the RBDS training room before baseline testing',
            'beat_summary' => 'Shay enters RBDS’s highly specialised training-room space for baseline testing and registers the physical machinery of the institution: a sleek, intimidating gym-like environment of mats and black-metal equipment that reframes dance preparation as disciplined bodily assessment.',
            'beat_type_id' => 'BEAT_ORIENTATION',
            'derivation_mode' => 'semantic_rule',
            'evidence' => array_values(array_filter([
                $hasGymCathedral ? 'gym_cathedral_description' : null,
                $hasTrainingMachines ? 'training_machines' : null,
                $hasPhysicalTestingContext ? 'physical_testing_context' : null,
            ])),
        ];
    }

    $hasExitAttempt = prose_contains_any($normalized, [
        'As I turn to exit',
        'intending to leave Mr Fruean',
        'leave Mr Fruean',
        'my path blocked',
        'path blocked',
    ]);

    $hasDoorwayInterception = prose_contains_any($normalized, [
        'heavy grey door is now ajar',
        'Leaning against the frame is Chloe',
        'waiting for me',
        'Chloe is waiting',
        'doorway',
        'door frame',
    ]);

    $hasFrueanContext = prose_contains_any($normalized, [
        'Mr Fruean',
        'iron plates',
        'silent judgements',
        'silent judgments',
        'conditioning room',
    ]);

    $isDoorwayInterceptionFixture = $hasExitAttempt
        && $hasDoorwayInterception
        && $hasFrueanContext;

    if ($isDoorwayInterceptionFixture) {
        return [
            'title' => 'Chloe intercepts Shay after the Fruean assessment',
            'beat_summary' => 'As Shay attempts to leave the RBDS conditioning room after meeting Mr Fruean, Chloe is already waiting at the doorway and silently intercepts her, continuing Shay’s guided movement through the institution and reinforcing the controlled, procedural nature of her induction into the team system.',
            'beat_type_id' => 'BEAT_TRANSITION',
            'derivation_mode' => 'semantic_rule',
            'evidence' => array_values(array_filter([
                $hasExitAttempt ? 'exit_attempt' : null,
                $hasDoorwayInterception ? 'doorway_interception' : null,
                $hasFrueanContext ? 'Fruean_context' : null,
            ])),
        ];
    }

    return null;
}
