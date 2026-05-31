<?php

declare(strict_types=1);

/**
 * Chrysalis calendar-event scene signal extraction.
 *
 * This layer may inspect corpus language, but it does not decide final
 * calendar-event metadata. It emits broad semantic signals only.
 */
function extract_chrysalis_calendar_event_scene_signals(
    string $proseBody
): array {

    $normalized = prose_normalized_body($proseBody);

    if ($normalized === '') {
        return [];
    }

    $signals = [];

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'institution.rbds', [
        'Royal Ballroom Dance Society',
        'RBDS',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'space.public_entry', [
        'front doors',
        'entrance',
        'sign in at the desk',
        'reception desk',
        'ledger',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'space.institutional_corridor', [
        'corridor',
        'hallway',
        'towards the offices and studios',
        'toward the offices and studios',
        'end of the hall',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'movement.deeper_into_institution', [
        'down the hallway',
        'down the corridor',
        'towards the offices and studios',
        'toward the offices and studios',
        'towards Ms Kingsley\'s office',
        'toward Ms Kingsley\'s office',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'movement.threshold_crossing', [
        'doors swing',
        'step inside',
        'stepped inside',
        'as I enter',
        'as I entered',
        'inside the entrance',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'procedure.intake', [
        'sign in',
        'reception desk',
        'ledger',
        'registers',
        'registered',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'authority.appointment', [
        'appointment',
        'expecting you',
        'office',
        'last door on the right',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'role.assignment', [
        'movement analyst',
        'Narrative Consultant',
        'Follow #8',
        'newly appointed',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'role.consultant_mandate', [
        'consultant',
        'hired gun',
        'board',
        'mandate',
        'sanctioned my three-month tenure',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'procedure.assessment', [
        'baseline testing',
        'baseline test',
        'testing setup',
        'assessment',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'movement.redirected', [
        'led',
        'directed',
        'sent',
        'summoned',
        'waiting for me',
        'path blocked',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'movement.vertical_transition', [
        'down into',
        'down to',
        'descend',
        'descends',
        'training-room level',
        'training level',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'space.training_or_conditioning', [
        'training room',
        'training-room',
        'conditioning room',
        'gym',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'space.physical_assessment', [
        'physical optimisation',
        'physical optimization',
        'matted floor',
        'black-metal machines',
        'iron plates',
        'machines',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'interaction.interruption', [
        'path blocked',
        'waiting for me',
        'Leaning against the frame',
        'turn to exit',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'movement.exit_attempt', [
        'turn to exit',
        'intending to leave',
        'leave',
        'exit',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'participant.named_authority_or_staff', [
        'Mrs Higgins',
        'Mrs. Higgins',
        'Ms Kingsley',
        'Chloe',
        'Mr Fruean',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'participant.enters_scene', [
        'A figure emerges',
        'emerges from a doorway',
        'walks towards me',
        'walks toward me',
        'draws closer',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'participant.approaches_focal_character', [
        'spots me immediately',
        'locks onto me',
        'detaches himself from the pack',
        'saunters over',
        'comes up',
        'steps into the light',
        'walks towards me',
        'walks toward me',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'participant.named_identification', [
        'Mr Kai Blackwood',
        'Kai Blackwood',
        'Mr Blackwood',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'role.team_role_identified', [
        'captain of the Standard formation team',
        'captain',
        'Standard formation team',
        'Lead Number One',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'interaction.first_direct_address', [
        'Forgive me',
        'Miss Vertue',
        'is it not',
        'We\'ve been expecting you',
        'You must be the reinforcement',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'interaction.welcoming_or_social_opening', [
        'You must be the reinforcement',
        'I\'d shake your hand',
        'Welcome to the asylum',
        'currently two civilians having an illicit conversation',
        'chief apologist for the humidity',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'recognition.prior_reputation', [
        'recognise you from your work',
        'I believe I recognise you',
        'Worlds',
        'American Latin team',
        'four consecutive world titles',
        'Latin formation',
        'I\'ve read about you in the manual',
    ]);

    chrysalis_add_scene_signal_if_any($signals, $normalized, 'recognition.charged_mutual', [
        'recognition',
        'static shock',
        'peace shatters',
        'shared, unnerving secret',
        'utterly, completely seen',
        'I think I remember you',
    ]);

    return array_values(array_unique($signals));
}

function chrysalis_add_scene_signal_if_any(
    array &$signals,
    string $normalized,
    string $signal,
    array $needles
): void {

    if (prose_contains_any($normalized, $needles)) {
        $signals[] = $signal;
    }
}
