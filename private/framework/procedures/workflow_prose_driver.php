<?php
declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_draft_creator.php';
require_once __DIR__ . '/../prose/prose_subevent_segmenter.php';
require_once __DIR__ . '/workflow_value_resolver.php';

function fw_execute_workflow_prose_create_draft(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $result = create_prose_draft($pdo, $payload);

    return [
        'success' => true,
        'context' => array_merge(
            $context,
            [
                'created_prose' => $result,
            ]
        ),
    ];
}


/*
|--------------------------------------------------------------------------
| NEW: PROSE DISPATCH LAYER (Tier 3 ready)
|--------------------------------------------------------------------------
*/

function fw_execute_workflow_prose_segment_subevents(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $prose = $payload['prose'] ?? null;

    if (!is_string($prose) || trim($prose) === '') {
        return [
            'success' => false,
            'error' => 'Missing prose for segmentation',
        ];
    }

    require_once __DIR__ . '/workflow_artifact_builder.php';

    $subevents = segment_prose_into_subevents($prose);

    $artifact = build_subevent_segmentation_artifact(
        $subevents
    );

    return [
        'success' => true,

        'context' => array_merge(
            $context,
            [
                'artifact' => $artifact,
            ]
        ),
    ];
}
