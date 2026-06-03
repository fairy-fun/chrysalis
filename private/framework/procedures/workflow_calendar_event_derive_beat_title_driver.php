<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_metadata_deriver.php';
require_once __DIR__
    . '/../../corpus/chrysalis/ontology/calendar_event_semantic_deriver.php';
require_once __DIR__ . '/../calendar/calendar_event_metadata_applier.php';
require_once __DIR__ . '/../calendar/calendar_event_ontology_applier.php';
require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__
    . '/../prose/resolve_calendar_event_attached_prose.php';

function fw_execute_workflow_calendar_event_derive_beat_title(
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
        throw new RuntimeException(
            'Missing calendar_event_entity_id for beat/title derivation'
        );
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
            notes
        FROM calendar_events
        WHERE entity_id = :entity_id
        LIMIT 1
    ");

    $eventStmt->execute([
        ':entity_id' => $entityId,
    ]);

    $calendarEvent = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($calendarEvent)) {
        throw new RuntimeException(
            'Calendar event not found for entity_id: ' . $entityId
        );
    }

    if (($calendarEvent['layer_id'] ?? null) !== 'calendar_layer_event') {
        throw new RuntimeException(
            'Expected calendar_layer_event for beat/title derivation'
        );
    }

    $attachedProse = resolve_calendar_event_attached_prose(
        $pdo,
        $entityId
    );

    $resolvedProseDraft = $attachedProse['prose_draft'] ?? [];
    $resolvedProseFamily = $attachedProse['prose_family'] ?? [];

    $proseBody = trim((string)($attachedProse['prose_body'] ?? ''));

    if ($proseBody === '') {
        throw new RuntimeException(
            'Attached prose draft has empty prose_body'
        );
    }

    $proseEntityId = (string)($resolvedProseDraft['entity_id'] ?? '');
    $proseDraftId = (int)($resolvedProseDraft['id'] ?? 0);
    $proseFamilyId = (int)($resolvedProseFamily['id'] ?? 0);

    $metadata = derive_prose_metadata(
        $proseBody,
        [
            'calendar_event' => $calendarEvent,
            'prose_resolution_topology' => 'attachment_topology',
            'resolved_prose_family' => $resolvedProseFamily,
            'resolved_prose_draft' => $resolvedProseDraft,
            'semantic_metadata_deriver'
                => 'derive_chrysalis_calendar_event_semantic_metadata',
        ]
    );

    $derivationMode = (string)($metadata['derivation_mode'] ?? 'unknown');

    $derivedTitle = trim((string)($metadata['title'] ?? ''));
    $derivedBeat = trim((string)($metadata['beat_summary'] ?? ''));
    $resolvedBeatTypeId = trim((string)($metadata['beat_type_id'] ?? ''));
    $derivedBeatCode = mb_strtolower(trim((string)($metadata['beat_code'] ?? '')));

    $isSemanticDerivation = in_array(
        $derivationMode,
        [
            'semantic_rule',
            'semantic_scene_class',
        ],
        true
    );

    if (
        !$isSemanticDerivation
        || $derivedTitle === ''
        || $derivedBeat === ''
    ) {
        return [
            'success' => false,
            'status' => 'semantic_derivation_unresolved',
            'workflow' => 'calendar_event_derive_beat_title',
            'tier' => 3,
            'entity_id' => $entityId,
            'transition_reason' => 'semantic_derivation_not_matched',
            'context' => array_merge(
                $context,
                [
                    'beat_title_derivation' => [
                        'calendar_event_entity_id' => $entityId,
                        'prose_entity_id' => $proseEntityId,
                        'prose_draft_id' => ($proseDraftId > 0) ? $proseDraftId : null,
                        'prose_family_id' => ($proseFamilyId > 0) ? $proseFamilyId : null,
                        'prose_projection_id' => 'attachment_topology',
                        'derivation_mode' => $derivationMode,
                        'semantic_resolved' => false,
                        'beat_code_hint' => ($derivedBeatCode !== '') ? $derivedBeatCode : null,
                        'resolved_beat_type_id' => null,
                        'extractive_title_candidate' => $metadata['extractive_title_candidate'] ?? null,
                        'extractive_summary' => $metadata['summary'] ?? null,
                        'evidence' => $metadata['evidence'] ?? [],
                        'applied_calendar_event_metadata' => null,
                        'applied_calendar_event_beat_type' => null,
                        'ontology_linkage_fields_touched' => [],
                        'diagnostic' => 'No deterministic semantic beat/title rule matched the attached prose. Calendar metadata was not mutated.',
                    ],
                ]
            ),
        ];
    }

    $appliedMetadata = apply_calendar_event_metadata(
        $pdo,
        $entityId,
        $derivedTitle,
        $derivedBeat
    );

    $appliedBeatType = null;

    if ($derivedBeatCode !== '') {
        $appliedBeatType = apply_calendar_event_beat_code(
            $pdo,
            $entityId,
            $derivedBeatCode
        );
    } elseif ($resolvedBeatTypeId !== '') {
        $appliedBeatType = apply_calendar_event_beat_type(
            $pdo,
            $entityId,
            $resolvedBeatTypeId
        );
    }

    $finalBeatTypeId = trim((string)(
        $appliedBeatType['beat_type_id']
        ?? $resolvedBeatTypeId
    ));

    $finalBeatCode = trim((string)(
        $appliedBeatType['beat_code']
        ?? $derivedBeatCode
    ));

    $ontologyLinkageFieldsTouched = array_values(array_unique(array_merge(
        is_array($appliedMetadata['ontology_linkage_fields_touched'] ?? null)
            ? $appliedMetadata['ontology_linkage_fields_touched']
            : [],
        is_array($appliedBeatType['ontology_linkage_fields_touched'] ?? null)
            ? $appliedBeatType['ontology_linkage_fields_touched']
            : []
    )));

    $characterSuggestionHandoff = [
        'recommended_next_workflow_family' => 'semantic_suggestions',
        'recommended_next_workflow' => 'calendar_event_suggest_characters',
        'recommended_next_user_action' => 'Tag Characters from the attached prose.',
        'suggestion_target' => [
            'target_entity_type' => 'calendar_event',
            'target_entity_id' => $entityId,
            'source_entity_type' => 'prose_draft',
            'source_entity_id' => $proseEntityId,
            'source_projection_id' => 'attachment_topology',
            'source_prose_draft_id' => ($proseDraftId > 0) ? $proseDraftId : null,
            'source_prose_family_id' => ($proseFamilyId > 0) ? $proseFamilyId : null,
        ],
        'doctrine' => [
            'derive_* produces semantic metadata.',
            'suggest_* produces reversible evidence-backed recommendations.',
            'apply_* is the only persistence boundary.',
            'Do not collapse semantic suggestion into persistence.',
        ],
        'first_pass_scope' => [
            'suggestion_type' => 'character',
            'strategy' => 'deterministic_only',
            'allowed_evidence' => [
                'exact_names',
                'aliases',
                'nicknames',
                'honorifics',
                'self_identification',
            ],
            'disallowed_evidence' => [
                'embeddings',
                'vector_similarity',
                'ungrounded_fuzzy_inference',
            ],
        ],
        'expected_payload_shape' => [
            'suggestions' => [
                'characters' => [
                    [
                        'suggestion_type' => 'character',
                        'resolved_entity_id' => 'CHAR-MAIN-001',
                        'confidence' => 0.99,
                        'evidence' => [
                            [
                                'type' => 'exact_name_match',
                                'text' => 'Shay',
                            ],
                        ],
                        'offsets' => [
                            [
                                'start' => 0,
                                'end' => 4,
                            ],
                        ],
                        'status' => 'suggested',
                    ],
                ],
            ],
        ],
        'likely_character_surface_forms' => [
            'Shay',
            'Chloe',
            'Ms Kingsley',
            'Lenore Kingsley',
        ],
        'safety' => [
            'suggestions_are_advisory' => true,
            'suggestions_are_reversible' => true,
            'suggestions_require_evidence' => true,
            'mutates_canonical_ontology' => false,
        ],
    ];

    return [
        'success' => true,
        'status' => 'ok',
        'workflow' => 'calendar_event_derive_beat_title',
        'tier' => 3,
        'entity_id' => $entityId,
        'context' => array_merge(
            $context,
            [
                'beat_title_derivation' => [
                    'calendar_event_entity_id' => $entityId,
                    'prose_entity_id' => $proseEntityId,
                    'prose_draft_id' => ($proseDraftId > 0) ? $proseDraftId : null,
                    'prose_family_id' => ($proseFamilyId > 0) ? $proseFamilyId : null,
                    'prose_projection_id' => 'attachment_topology',
                    'derived_title' => $derivedTitle,
                    'derived_beat' => $derivedBeat,
                    'beat_code_hint' => ($derivedBeatCode !== '') ? $derivedBeatCode : null,
                    'resolved_beat_code' => ($finalBeatCode !== '') ? $finalBeatCode : null,
                    'resolved_beat_type_id' => ($finalBeatTypeId !== '') ? $finalBeatTypeId : null,
                    'derivation_mode' => $derivationMode,
                    'evidence' => $metadata['evidence'] ?? [],
                    'applied_calendar_event_metadata' => $appliedMetadata,
                    'applied_calendar_event_beat_type' => $appliedBeatType,
                    'ontology_linkage_fields_touched' => $ontologyLinkageFieldsTouched,
                ],
                'handoff_packet' => [
                    'workflow_stage' => 'beat_title_derived_and_applied',
                    'derivation' => [
                        'workflow_id' => 'calendar_event_derive_beat_title',
                        'derivation_type' => 'metadata_only',
                        'derivation_mode' => $derivationMode,
                    ],
                    'canonical' => [
                        'calendar_event_entity_id' => $entityId,
                        'prose_entity_id' => $proseEntityId,
                        'prose_projection_id' => 'attachment_topology',
                        'prose_draft_id' => ($proseDraftId > 0) ? $proseDraftId : null,
                        'prose_family_id' => ($proseFamilyId > 0) ? $proseFamilyId : null,
                    ],
                    'derived' => [
                        'title' => $derivedTitle,
                        'beat' => $derivedBeat,
                        'beat_code' => ($finalBeatCode !== '') ? $finalBeatCode : null,
                        'beat_type_id' => ($finalBeatTypeId !== '') ? $finalBeatTypeId : null,
                    ],
                    'apply_state' => [
                        'semantic_text_persisted' => true,
                        'beat_ontology_persisted' => ($appliedBeatType !== null),
                        'ontology_linkage_fields_touched' => $ontologyLinkageFieldsTouched,
                        'topology_generated' => false,
                        'updated_rows' => [
                            'semantic_text' => $appliedMetadata['updated_rows'] ?? null,
                            'beat_type' => $appliedBeatType['updated_rows'] ?? null,
                        ],
                    ],
                    'future_workflow' => [
                        'workflow_id' => 'calendar_event_process_attached_prose',
                        'intent' => 'Optionally segment attached prose into calendar subevents later.',
                    ],
                    'recommended_next_step' => $characterSuggestionHandoff,
                ],
            ]
        ),
    ];
}
