<?php

declare(strict_types=1);

require_once __DIR__ . '/../calendar/prose_calendar_orchestrator.php';

function fw_execute_workflow_calendar_event_process_attached_prose(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    /**
     * Resolve canonical calendar event identity from workflow context
     */
    $entityId = $context['calendar_event']['entity_id'] ?? null;

    if (!is_string($entityId) || trim($entityId) === '') {
        throw new RuntimeException(
            'Missing calendar_event.entity_id in workflow context'
        );
    }

    $entityId = trim($entityId);

    /**
     * Resolve canonically attached prose:
     *
     * calendar_event
     *   → prose_projections.target_entity_id
     *   → prose_drafts.prose_body
     */
    $stmt = $pdo->prepare("
    SELECT
        pd.prose_body
    FROM prose_projections pp
    INNER JOIN prose_drafts pd
        ON pd.id = pp.published_prose_draft_id
    WHERE pp.target_entity_id = :entity_id
    ORDER BY pp.id DESC
    LIMIT 1
");

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'No prose projection attached to calendar event'
        );
    }

    $prose = trim((string)($row['prose_body'] ?? ''));

    if ($prose === '') {
        throw new RuntimeException(
            'Attached prose draft has empty prose_body'
        );
    }

    /**
     * Tier 3 orchestration boundary
     * - segmentation
     * - idempotency guards
     * - persistence into calendar_events
     */
    $result = execute_calendar_batch_from_prose(
        $pdo,
        $entityId,
        $prose
    );

    require_once __DIR__ . '/workflow_artifact_builder.php';

    $artifact = build_calendar_subevent_artifact_graph(
        $entityId,
        $result
    );

    return [
        'status' => 'ok',
        'workflow' => 'calendar_event_process_attached_prose',
        'tier' => 3,
        'entity_id' => $entityId,

        /**
         * Raw execution result (DB + orchestration truth)
         */
        'execution' => $result,

        /**
         * Artifact MUST be placed into context for workflow engine merging
         */
        'context' => [
            'artifact' => $artifact,
        ],
    ];
}