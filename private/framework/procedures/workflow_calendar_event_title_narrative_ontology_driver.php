<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_metadata_deriver.php';
require_once __DIR__ . '/../calendar/calendar_event_metadata_applier.php';
require_once __DIR__ . '/../calendar/calendar_event_ontology_applier.php';
require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__
    . '/../prose/resolve_calendar_event_attached_prose.php';

function fw_execute_workflow_calendar_event_title_narrative_ontology(
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

    $entityId = trim((string)(
        $payload['calendar_event_entity_id']
        ?? $context['calendar_event']['entity_id']
        ?? ''
    ));

    if ($entityId === '') {
        throw new RuntimeException('Missing calendar_event_entity_id');
    }

    $eventStmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            layer_id,
            projection_id,
            book_time_id,
            event_index,
            parent_event_id,
            subevent_index,
            sequence_index,
            summary,
            notes,
            beat_type_id,
            class_type_id
        FROM calendar_events
        WHERE entity_id = :entity_id
        LIMIT 1
    ");

    $eventStmt->execute([
        ':entity_id' => $entityId,
    ]);

    $calendarEvent = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($calendarEvent)) {
        throw new RuntimeException('Calendar event not found for entity_id: ' . $entityId);
    }

    if (($calendarEvent['layer_id'] ?? null) !== 'calendar_layer_event') {
        throw new RuntimeException('Expected calendar_layer_event');
    }

    $attachedProse = resolve_calendar_event_attached_prose(
        $pdo,
        $entityId
    );

    $proseBody = trim((string)(
        $attachedProse['prose_body'] ?? ''
    ));

    $resolvedProseDraft = $attachedProse['prose_draft'] ?? [];
    $resolvedProseFamily = $attachedProse['prose_family'] ?? [];

    if (!is_array($proseRow)) {
        throw new RuntimeException('No primary timeline-view prose projection attached to calendar event: ' . $entityId);
    }

    $proseBody = trim((string)($proseRow['prose_body'] ?? ''));

    if ($proseBody === '') {
        throw new RuntimeException('Attached prose draft has empty prose_body');
    }

    $metadata = derive_prose_metadata(
        $proseBody,
        [
            'calendar_event' => $calendarEvent,
            'prose_projection' => $proseRow,
        ]
    );

    $derivationMode = (string)($metadata['derivation_mode'] ?? 'unknown');
    $derivedTitle = trim((string)($metadata['title'] ?? ''));
    $derivedNarrativeSummary = trim((string)($metadata['beat_summary'] ?? ''));
    $resolvedBeatTypeId = trim((string)($metadata['beat_type_id'] ?? ''));

    if (
        $derivationMode !== 'semantic_rule'
        || $derivedTitle === ''
        || $derivedNarrativeSummary === ''
    ) {
        return [
            'success' => false,
            'status' => 'semantic_derivation_unresolved',
            'workflow' => 'calendar_event_title_narrative_ontology',
            'entity_id' => $entityId,
            'context' => array_merge($context, [
                'title_narrative_ontology_derivation' => [
                    'calendar_event_entity_id' => $entityId,
                    'prose_entity_id' => (string)($resolvedProseDraft['entity_id'] ?? ''),
                    'prose_projection_id' => 'attachment_topology',
                    'derivation_mode' => $derivationMode,
                    'semantic_text_resolved' => false,
                    'ontology_resolved' => false,
                    'extractive_title_candidate' => $metadata['extractive_title_candidate'] ?? null,
                    'extractive_summary' => $metadata['summary'] ?? null,
                    'evidence' => $metadata['evidence'] ?? [],
                ],
            ]),
        ];
    }

    $appliedMetadata = apply_calendar_event_metadata(
        $pdo,
        $entityId,
        $derivedTitle,
        $derivedNarrativeSummary
    );

    $appliedBeatType = null;

    if ($resolvedBeatTypeId !== '') {
        $appliedBeatType = apply_calendar_event_beat_type(
            $pdo,
            $entityId,
            $resolvedBeatTypeId
        );
    }

    return [
        'success' => true,
        'status' => 'ok',
        'workflow' => 'calendar_event_title_narrative_ontology',
        'entity_id' => $entityId,
        'context' => array_merge($context, [
            'title_narrative_ontology_derivation' => [
                'calendar_event_entity_id' => $entityId,
                'prose_entity_id' => (string)($resolvedProseDraft['entity_id'] ?? ''),
                'prose_projection_id' => 'attachment_topology',
                'derived_title' => $derivedTitle,
                'derived_narrative_summary' => $derivedNarrativeSummary,
                'summary_target_field' => 'calendar_events.summary',
                'notes_target_field' => 'calendar_events.notes',
                'resolved_beat_type_id' => ($resolvedBeatTypeId !== '') ? $resolvedBeatTypeId : null,
                'beat_type_target_field' => 'calendar_events.beat_type_id',
                'resolved_class_type_id' => null,
                'class_type_target_field' => 'calendar_events.class_type_id',
                'derivation_mode' => $derivationMode,
                'evidence' => $metadata['evidence'] ?? [],
                'applied_semantic_text' => $appliedMetadata,
                'applied_beat_ontology' => $appliedBeatType,
            ],
            'handoff_packet' => [
                'workflow_stage' => 'title_narrative_summary_and_optional_beat_type_applied',
                'derived' => [
                    'title' => $derivedTitle,
                    'narrative_summary' => $derivedNarrativeSummary,
                    'beat_type_id' => ($resolvedBeatTypeId !== '') ? $resolvedBeatTypeId : null,
                    'class_type_id' => null,
                ],
                'apply_state' => [
                    'semantic_text_persisted' => true,
                    'beat_ontology_persisted' => ($appliedBeatType !== null),
                    'class_ontology_persisted' => false,
                    'topology_generated' => false,
                ],
            ],
        ]),
    ];
}
