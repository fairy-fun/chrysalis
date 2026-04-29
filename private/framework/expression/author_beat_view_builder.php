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
    $highTensionCandidates = [];
    $recommendedPaths = [
        'stable' => null,
        'exploratory' => null,
        'disruptive' => null,
    ];

    // --- RAW ORDER CAPTURE ---
    $rawOrderedBeats = $rankedNextBeats;

    $rawRankMap = [];
    foreach ($rawOrderedBeats as $i => $beat) {
        if (!empty($beat['theme_entity_id'])) {
            $rawRankMap[$beat['theme_entity_id']] = $i + 1;
        }
    }

    if ($mode === 'PROPOSE_FORWARD' && $lastTheme) {
        foreach ($rankedNextBeats as $index => $suggestion) {
            if (empty($suggestion['theme_entity_id'])) {
                continue;
            }

            $themeId = $suggestion['theme_entity_id'];

            $audit = auditCharacterPredictionDrift(
                $pdo,
                $themeId,
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
            $rankedNextBeats[$index]['raw_score_rank'] = $rawRankMap[$themeId] ?? null;

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

        // --- DRIFT RANK ASSIGNMENT ---
        foreach ($rankedNextBeats as $i => &$beat) {
            $beat['drift_adjusted_rank'] = $i + 1;
            $beat['is_high_tension'] = false;
            $beat['tension_label'] = 'LOW_TENSION';

            if (isset($beat['raw_score_rank'], $beat['drift_adjusted_rank'])) {
                $beat['rank_delta'] = $beat['raw_score_rank'] - $beat['drift_adjusted_rank'];
                $beat['rank_delta_abs'] = abs($beat['rank_delta']);

                if ($beat['rank_delta_abs'] >= 2) {
                    $beat['is_high_tension'] = true;
                    $beat['tension_label'] = $beat['rank_delta'] > 0
                        ? 'RARE_BUT_ALIGNED'
                        : 'COMMON_BUT_MISALIGNED';
                }
            }
        }
        unset($beat);

        $highTensionCandidates = array_values(array_filter(
            $rankedNextBeats,
            static fn (array $beat): bool => !empty($beat['is_high_tension'])
        ));

        usort($highTensionCandidates, static function (array $left, array $right): int {
            $leftDelta = isset($left['rank_delta_abs']) ? (int) $left['rank_delta_abs'] : 0;
            $rightDelta = isset($right['rank_delta_abs']) ? (int) $right['rank_delta_abs'] : 0;

            if ($leftDelta !== $rightDelta) {
                return $rightDelta <=> $leftDelta;
            }

            $leftAdjustedScore = isset($left['drift_adjusted_score'])
                ? (float) $left['drift_adjusted_score']
                : 0.0;

            $rightAdjustedScore = isset($right['drift_adjusted_score'])
                ? (float) $right['drift_adjusted_score']
                : 0.0;

            return $rightAdjustedScore <=> $leftAdjustedScore;
        });

        $recommendedPaths['stable'] = $rankedNextBeats[0] ?? null;

        foreach ($rankedNextBeats as $beat) {
            if (($beat['tension_label'] ?? null) === 'RARE_BUT_ALIGNED') {
                $recommendedPaths['exploratory'] = $beat;
                break;
            }
        }

        $recommendedPaths['disruptive'] = $highTensionCandidates[0] ?? null;
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

        'high_tension_candidates' => $mode === 'PROPOSE_FORWARD'
            ? $highTensionCandidates
            : [],

        'recommended_paths' => $mode === 'PROPOSE_FORWARD'
            ? $recommendedPaths
            : [
                'stable' => null,
                'exploratory' => null,
                'disruptive' => null,
            ],

        'drift_audit' => $mode === 'PROPOSE_FORWARD'
            ? $driftAudit
            : [],

        'author_view' => [
            'heading' => 'Shay Beat Sequence',
            'lines' => $lines,
        ],
    ];
}
