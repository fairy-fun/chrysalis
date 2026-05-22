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
     * Resolve canonical calendar event identity from workflow context.
     */
    $entityId = $context['calendar_event']['entity_id'] ?? null;

    if (!is_string($entityId) || trim($entityId) === '') {
        throw new RuntimeException(
            'Missing calendar_event.entity_id in workflow context'
        );
    }

    $entityId = trim($entityId);

    /**
     * Resolve canonically attached prose.
     *
     * calendar_event
     *   -> prose_projections.target_entity_id
     *   -> prose_drafts.published_prose_draft_id
     *   -> prose_drafts.prose_body
     */
    $stmt = $pdo->prepare("
    SELECT
        pp.id AS prose_projection_id,
        pd.id AS prose_draft_id,
        pd.entity_id AS prose_entity_id,
        pd.prose_body
    FROM prose_projections pp
    INNER JOIN prose_drafts pd
        ON pd.id = pp.published_prose_draft_id
    WHERE pp.target_entity_id = :entity_id
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

    $proseProjectionId =
        (int)($row['prose_projection_id'] ?? 0);

    $proseDraftId =
        (int)($row['prose_draft_id'] ?? 0);

    $proseEntityId =
        trim((string)($row['prose_entity_id'] ?? ''));

    $prose = trim((string)($row['prose_body'] ?? ''));

    if ($prose === '') {
        throw new RuntimeException(
            'Attached prose draft has empty prose_body'
        );
    }

    /**
     * Resolve canonical event identity only.
     *
     * chronology_address is render-only and must not participate
     * in locality or orchestration authority.
     */
    $stmt = $pdo->prepare("
    SELECT
        id,
        entity_id,
        parent_event_id,
        projection_id,
        layer_id,
        book_time_id,
        event_index,
        subevent_index,
        sequence_index,
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

    /**
     * Tier 3 orchestration boundary.
     */
    $result = orchestrate_prose_semantics(
        $pdo,
        $calendarEvent,
        $prose
    );

    $artifact = build_calendar_subevent_artifact_graph(
        $entityId,
        $result
    );

    $subeventCount =
        (int)($result['subevent_count'] ?? 0);

    $beatCount =
        (int)($result['beat_count'] ?? 0);

    $summaryCount =
        (int)($result['summary_count'] ?? 0);

    return [
        'success' => true,
        'status' => 'ok',
        'workflow' => 'calendar_event_process_attached_prose',
        'tier' => 3,
        'entity_id' => $entityId,

        'execution' => $result,

        'context' => [
            'artifact' => $artifact,

            'handoff_packet' => [
                'workflow_stage' => 'prose_processed',

                'canonical' => [
                    'calendar_event_entity_id'
                        => $entityId,

                    'prose_entity_id'
                        => $proseEntityId,

                    'prose_projection_id'
                        => $proseProjectionId,
                ],

                'derived' => [
                    'subevent_count'
                        => $subeventCount,

                    'beat_count'
                        => $beatCount,

                    'summary_count'
                        => $summaryCount,
                ],

                'next_workflow' => [
                    'workflow_id'
                        => 'calendar_day_display_prose',
                ],
            ],
        ],
    ];
}
