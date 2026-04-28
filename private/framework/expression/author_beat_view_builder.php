<?php

require_once __DIR__ . '/character_beat_label_suggester.php';

function buildAuthorBeatView(
    PDO $pdo,
    string $characterEntityId,
    ?string $projectionEntityId,
    string $mode
): array {
    if (!in_array($mode, ['READ_BASELINE', 'PROPOSE_FORWARD'], true)) {
        return [
            'status' => 'error',
            'message' => 'Invalid mode',
            'allowed_modes' => ['READ_BASELINE', 'PROPOSE_FORWARD'],
        ];
    }

    $suggestionResult = suggestCharacterBeatLabels(
        $pdo,
        $characterEntityId,
        $projectionEntityId
    );

    $observedBeats = [];
    $lines = [];

    foreach ($suggestionResult['proposals'] as $proposal) {
        $beat = [
            'chronology_address' => $proposal['chronology_address'],
            'theme' => $proposal['theme_entity_id'],
            'beat' => $proposal['beat_label'],
        ];

        $observedBeats[] = $beat;
        $lines[] = $proposal['chronology_address'] . ' → ' . $proposal['beat_label'];
    }

    return [
        'status' => 'ok',
        'character_entity_id' => $characterEntityId,
        'projection_entity_id' => $projectionEntityId,
        'mode' => $mode,
        'sequence_status' => 'pass',
        'observed_beats' => $mode === 'READ_BASELINE' ? $observedBeats : [],
        'suggested_beats' => $mode === 'PROPOSE_FORWARD' ? $suggestionResult['proposals'] : [],
        'author_view' => [
            'heading' => 'Shay Beat Sequence',
            'lines' => $lines,
        ],
    ];
}