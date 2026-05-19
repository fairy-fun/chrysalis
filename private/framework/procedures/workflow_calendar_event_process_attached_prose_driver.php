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
     * Resolve prose payload
     * (supports either input or action payload for flexibility)
     */
    $stmt = $pdo->prepare("
    SELECT prose_body
    FROM calendar_events
    WHERE entity_id = :entity_id
    LIMIT 1
");

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $prose = trim((string)($row['prose_body'] ?? ''));

    if ($prose === '') {
        throw new RuntimeException(
            'No prose_body attached to calendar event'
        );
    }

    if (!is_string($prose)) {
        throw new RuntimeException('Invalid prose payload type');
    }

    $prose = trim($prose);

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