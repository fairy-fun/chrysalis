<?php

declare(strict_types=1);

/**
 * Chrysalis calendar-event metadata mapping.
 *
 * This layer maps broad scene classes to provisional calendar-event metadata.
 * It is intentionally conservative: unresolved is safer than stale specificity.
 */
function map_chrysalis_scene_class_to_calendar_event_metadata(
    array $classification,
    array $signals,
    array $context = []
): ?array {

    $sceneClass = (string)($classification['scene_class'] ?? '');

    if ($sceneClass === 'character_introduction') {
        return chrysalis_calendar_event_scene_metadata(
            'Shay encounters Kai Blackwood in the RBDS corridor',
            'As Shay moves deeper into RBDS from the lobby toward Ms Kingsley’s office, she encounters Kai Blackwood, captain of the Standard formation team. Their brief exchange establishes his controlled stillness, his awareness of her Latin formation history, and the unsettling mutual recognition that interrupts her professional composure before he moves on.',
            'BEAT_ORIENTATION',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'institutional_intake') {
        return chrysalis_calendar_event_scene_metadata(
            'Institutional intake at RBDS',
            'The prose depicts an institutional intake moment at RBDS, combining entry, reception, appointment, or role-assignment signals as the character is formally routed into the organisation.',
            'BEAT_TRANSITION',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'procedural_assessment_handoff') {
        return chrysalis_calendar_event_scene_metadata(
            'Procedural handoff into assessment',
            'The prose depicts a procedural handoff from authority or role context into assessment, with the character redirected toward testing, training, or another evaluative space.',
            'BEAT_INSTRUCTION',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'assessment_environment_orientation') {
        return chrysalis_calendar_event_scene_metadata(
            'Orientation to an assessment environment',
            'The prose depicts orientation to a physical or training environment where assessment, bodily discipline, or role evaluation becomes the dominant institutional frame.',
            'BEAT_ORIENTATION',
            $classification,
            $signals
        );
    }

    if ($sceneClass === 'procedural_interruption_or_redirect') {
        return chrysalis_calendar_event_scene_metadata(
            'Procedural interruption or redirect',
            'The prose depicts an interruption or redirect that prevents clean exit and continues the character’s movement through an institutional procedure.',
            'BEAT_TRANSITION',
            $classification,
            $signals
        );
    }

    return null;
}

function chrysalis_calendar_event_scene_metadata(
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
        'confidence' => (float)($classification['confidence'] ?? 0.0),
        'evidence' => [
            'signals' => array_values($signals),
            'required_evidence' => array_values(
                $classification['required_evidence'] ?? []
            ),
        ],
    ];
}
