<?php

declare(strict_types=1);

require_once __DIR__
    . '/semantic_resolution_stage_result.php';

require_once __DIR__
    . '/semantic_candidate_arbitrator.php';

require_once __DIR__
    . '/semantic_resolution_persister.php';

function semantic_resolution_workflow_runner_run(
    PDO $pdo,
    array $stageResults,
    array $options = []
): array {

    $allCandidates = [];

    foreach ($stageResults as $stageResult) {

        if (!is_array($stageResult)) {
            continue;
        }

        foreach (
            ($stageResult['candidates'] ?? [])
            as $candidate
        ) {

            if (!is_array($candidate)) {
                continue;
            }

            $allCandidates[] = $candidate;
        }
    }

    $arbitration =
        semantic_candidate_arbitrator_run(
            $allCandidates,
            [
                'arbitration_stage' => (
                    $options['arbitration_stage']
                    ?? 'semantic_resolution_workflow'
                ),
            ]
        );

    $persistence =
        semantic_resolution_persister_persist(
            $pdo,
            [
                'stage_results' => $stageResults,
                'arbitration' => $arbitration,
            ]
        );

    return [
        'selected_candidate' => (
            $arbitration['selected_candidate']
            ?? null
        ),

        'all_candidates' => (
            $arbitration['all_candidates']
            ?? []
        ),

        'persistence' => $persistence,
    ];
}