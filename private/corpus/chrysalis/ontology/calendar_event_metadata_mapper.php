<?php

declare(strict_types=1);

/**
 * Chrysalis calendar-event metadata mapping.
 *
 * This layer maps broad scene classes to provisional calendar-event metadata.
 * It is intentionally conservative: unresolved is safer than stale specificity.
 *
 * Important boundary:
 * - corpus mapping may emit semantic beat code hints
 * - workflow/runtime code must resolve those hints against the event's
 *   classset before persisting calendar_events.beat_type_id
 */
function map_chrysalis_scene_class_to_calendar_event_metadata(
    array $classification,
    array $signals,
    array $context = []
): ?array {

    $sceneClass = (string)($classification['scene_class'] ?? '');

    if ($sceneClass === 'character_introduction') {
        return chrysalis_calendar_event_scene_metadata(
            'A salient figure is introduced',
            'The prose introduces a newly salient figure through approach, first address, role or status framing, and the first stabilizing exchange that makes the figure narratively consequential within the scene.',
            'interaction',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'charged_recognition') {
        return chrysalis_calendar_event_scene_metadata(
            'Shay and Kai recognise one another',
            'The prose centers a charged recognition encounter in which identification, address, or prior awareness sharpens the interpersonal stakes and reframes the scene around what passes between Shay and Kai when they fully register one another.',
            'interaction',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'institutional_intake') {
        return chrysalis_calendar_event_scene_metadata(
            'Institutional intake at RBDS',
            'The prose depicts an institutional intake moment at RBDS, combining entry, reception, appointment, or role-assignment signals as the character is formally routed into the organisation.',
            'transition',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'procedural_assessment_handoff') {
        return chrysalis_calendar_event_scene_metadata(
            'Procedural handoff into assessment',
            'The prose depicts a procedural handoff from authority or role context into assessment, with the character redirected toward testing, training, or another evaluative space.',
            'instruction',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'assessment_environment_orientation') {
        return chrysalis_calendar_event_scene_metadata(
            'Orientation to an assessment environment',
            'The prose depicts orientation to a physical or training environment where assessment, bodily discipline, or role evaluation becomes the dominant institutional frame.',
            'evaluation',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'procedural_interruption_or_redirect') {
        return chrysalis_calendar_event_scene_metadata(
            'Procedural interruption or redirect',
            'The prose depicts an interruption or redirect that prevents clean exit and continues the character’s movement through an institutional procedure.',
            'transition',
            $classification,
            $signals
        );
    }

    return null;
}

function chrysalis_calendar_event_scene_metadata(
    string $title,
    string $beatSummary,
    string $beatCode,
    array $classification,
    array $signals
): array {

    return [
        'title' => $title,
        'beat_summary' => $beatSummary,
        'beat_code' => mb_strtolower(trim($beatCode)),
        'beat_type_id' => null,
        'derivation_mode' => 'semantic_scene_class',
        'scene_class' => (string)($classification['scene_class'] ?? ''),
        'confidence' => (float)($classification['confidence'] ?? 0.0),
        'evidence' => [
            'signals' => array_values($signals),
            'required_evidence' => array_values(
                $classification['required_evidence'] ?? []
            ),
        ],
    ];
}
