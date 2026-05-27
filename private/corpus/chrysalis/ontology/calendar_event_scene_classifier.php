<?php

declare(strict_types=1);

/**
 * Chrysalis calendar-event scene classification.
 *
 * This layer receives broad signals and returns broad scene classes. It should
 * avoid exact prose-fixture recovery and should prefer unresolved output over
 * confident overclassification.
 */
function classify_chrysalis_calendar_event_scene(array $signals): ?array
{
    $classes = [];

    if (
        chrysalis_scene_has($signals, 'participant.enters_scene')
        && chrysalis_scene_has($signals, 'participant.named_identification')
        && chrysalis_scene_has_any($signals, [
            'role.team_role_identified',
            'interaction.first_direct_address',
            'recognition.prior_reputation',
        ])
    ) {
        $classes[] = chrysalis_scene_classification(
            'character_introduction',
            0.82,
            [
                'participant.enters_scene',
                'participant.named_identification',
                'team_role_address_or_reputation',
            ]
        );
    }

    if (
        chrysalis_scene_has($signals, 'institution.rbds')
        && chrysalis_scene_has_any($signals, [
            'space.public_entry',
            'movement.threshold_crossing',
        ])
        && chrysalis_scene_has_any($signals, [
            'procedure.intake',
            'authority.appointment',
            'role.assignment',
        ])
    ) {
        $classes[] = chrysalis_scene_classification(
            'institutional_intake',
            0.72,
            [
                'institution.rbds',
                'public_entry_or_threshold',
                'intake_appointment_or_role_assignment',
            ]
        );
    }

    if (
        chrysalis_scene_has_any($signals, [
            'authority.appointment',
            'role.assignment',
        ])
        && chrysalis_scene_has($signals, 'procedure.assessment')
        && chrysalis_scene_has_any($signals, [
            'movement.redirected',
            'movement.vertical_transition',
            'space.training_or_conditioning',
        ])
    ) {
        $classes[] = chrysalis_scene_classification(
            'procedural_assessment_handoff',
            0.74,
            [
                'authority_or_role_context',
                'procedure.assessment',
                'redirect_descent_or_training_space',
            ]
        );
    }

    if (
        chrysalis_scene_has_any($signals, [
            'space.training_or_conditioning',
            'space.physical_assessment',
        ])
        && chrysalis_scene_has_any($signals, [
            'procedure.assessment',
            'role.assignment',
        ])
    ) {
        $classes[] = chrysalis_scene_classification(
            'assessment_environment_orientation',
            0.7,
            [
                'training_or_physical_assessment_space',
                'assessment_or_role_context',
            ]
        );
    }

    if (
        chrysalis_scene_has($signals, 'interaction.interruption')
        && chrysalis_scene_has_any($signals, [
            'movement.exit_attempt',
            'movement.redirected',
        ])
        && chrysalis_scene_has($signals, 'participant.named_authority_or_staff')
    ) {
        $classes[] = chrysalis_scene_classification(
            'procedural_interruption_or_redirect',
            0.69,
            [
                'interaction.interruption',
                'exit_attempt_or_redirect',
                'participant.named_authority_or_staff',
            ]
        );
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

function chrysalis_scene_classification(
    string $sceneClass,
    float $confidence,
    array $requiredEvidence
): array {

    return [
        'scene_class' => $sceneClass,
        'confidence' => $confidence,
        'required_evidence' => array_values($requiredEvidence),
    ];
}

function chrysalis_scene_has(array $signals, string $signal): bool
{
    return in_array($signal, $signals, true);
}

function chrysalis_scene_has_any(array $signals, array $candidateSignals): bool
{
    foreach ($candidateSignals as $candidateSignal) {
        if (chrysalis_scene_has($signals, (string)$candidateSignal)) {
            return true;
        }
    }

    return false;
}
