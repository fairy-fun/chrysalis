<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_semantic_orchestrator.php';
require_once __DIR__ . '/workflow_artifact_builder.php';

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

    $stmt = $pdo->prepare("
    SELECT
        id,
        entity_id,
        parent_event_id,
        projection_id,
        chronology_address,
        layer_id,
        summary
    FROM calendar_events
    WHERE entity_id = :entity_id
    LIMIT 1
");

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $calendarEvent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($calendarEvent)) {
        throw new RuntimeException(
            'Calendar event not found for entity_id: ' . $entityId
        );
    }

    $result = orchestrate_prose_semantics(
        $pdo,
        $calendarEvent,
        $prose
    );


    $artifact = build_calendar_subevent_artifact_graph(
        $entityId,
        $result
    );

    return [
        'success' => true,
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