<?php

require_once __DIR__ . '/character_beat_label_suggester.php';
require_once __DIR__ . '/character_next_beat_suggester.php';
require_once __DIR__ . '/character_prediction_drift_auditor.php';

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

    foreach (($suggestionResult['proposals'] ?? []) as $proposal) {
        $beat = [
            'chronology_address' => $proposal['chronology_address'],
            'theme' => $proposal['theme_entity_id'],
            'beat' => $proposal['beat_label'],
        ];

        $observedBeats[] = $beat;
        $lines[] = $proposal['chronology_address'] . ' → ' . $proposal['beat_label'];
    }

    $lastTheme = !empty($observedBeats)
        ? $observedBeats[array_key_last($observedBeats)]['theme']
        : null;

    $nextBeatResult = [
        'status' => 'skipped',
        'suggestions' => [],
    ];

    if ($mode === 'PROPOSE_FORWARD' && $lastTheme) {
        $nextBeatResult = suggestNextCharacterBeat(
            $pdo,
            $characterEntityId,
            $projectionEntityId,
            $lastTheme
        );
    }

    $driftAudit = [];
    $rankedNextBeats = $nextBeatResult['suggestions'] ?? [];

    if ($mode === 'PROPOSE_FORWARD' && $lastTheme) {
        foreach ($rankedNextBeats as $index => $suggestion) {
            if (empty($suggestion['theme_entity_id'])) {
                continue;
            }

            $audit = auditCharacterPredictionDrift(
                $pdo,
                $suggestion['theme_entity_id'],
                $lastTheme
            );

            $score = isset($suggestion['score'])
                ? (float) $suggestion['score']
                : 0.0;

            $scoreMultiplier = isset($audit['score_multiplier'])
                ? (float) $audit['score_multiplier']
                : 0.0;

            $rankedNextBeats[$index]['drift'] = $audit;
            $rankedNextBeats[$index]['drift_adjusted_score'] = $score * $scoreMultiplier;
            $driftAudit[] = $audit;
        }

        usort($rankedNextBeats, static function (array $left, array $right): int {
            $leftAdjustedScore = isset($left['drift_adjusted_score'])
                ? (float) $left['drift_adjusted_score']
                : 0.0;

            $rightAdjustedScore = isset($right['drift_adjusted_score'])
                ? (float) $right['drift_adjusted_score']
                : 0.0;

            if ($leftAdjustedScore !== $rightAdjustedScore) {
                return $rightAdjustedScore <=> $leftAdjustedScore;
            }

            $leftScore = isset($left['score']) ? (float) $left['score'] : 0.0;
            $rightScore = isset($right['score']) ? (float) $right['score'] : 0.0;

            if ($leftScore !== $rightScore) {
                return $rightScore <=> $leftScore;
            }

            $leftSubjectHits = isset($left['subject_hits']) ? (int) $left['subject_hits'] : 0;
            $rightSubjectHits = isset($right['subject_hits']) ? (int) $right['subject_hits'] : 0;

            if ($leftSubjectHits !== $rightSubjectHits) {
                return $rightSubjectHits <=> $leftSubjectHits;
            }

            $leftGlobalHits = isset($left['global_hits']) ? (int) $left['global_hits'] : 0;
            $rightGlobalHits = isset($right['global_hits']) ? (int) $right['global_hits'] : 0;

            return $rightGlobalHits <=> $leftGlobalHits;
        });
    }

    return [
        'status' => 'ok',
        'character_entity_id' => $characterEntityId,
        'projection_entity_id' => $projectionEntityId,
        'mode' => $mode,
        'sequence_status' => 'pass',

        'observed_beats' => $mode === 'READ_BASELINE'
            ? $observedBeats
            : [],

        'suggested_beats' => $mode === 'PROPOSE_FORWARD'
            ? ($suggestionResult['proposals'] ?? [])
            : [],

        'suggested_next_beats' => $mode === 'PROPOSE_FORWARD'
            ? $rankedNextBeats
            : [],

        'drift_audit' => $mode === 'PROPOSE_FORWARD'
            ? $driftAudit
            : [],

        'author_view' => [
            'heading' => 'Shay Beat Sequence',
            'lines' => $lines,
        ],
    ];
}
