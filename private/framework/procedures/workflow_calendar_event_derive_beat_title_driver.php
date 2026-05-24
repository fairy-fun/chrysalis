<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_metadata_deriver.php';
require_once __DIR__ . '/../calendar/calendar_event_metadata_applier.php';
require_once __DIR__ . '/workflow_value_resolver.php';

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

    $proseStmt = $pdo->prepare("
        SELECT
            pp.id AS prose_projection_id,
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title AS prose_title,
            pd.summary AS prose_summary,
            pd.prose_body
        FROM prose_projections pp
        INNER JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
        WHERE pp.target_entity_id = :entity_id
          AND pp.published_prose_draft_id IS NOT NULL
          AND pp.role_id = 'prose_projection_role_primary'
          AND pp.projection_type_id = 'projection_type_timeline_view'
        ORDER BY
            pp.projection_order ASC,
            pp.id ASC
        LIMIT 1
    ");

    $proseStmt->execute([
        ':entity_id' => $entityId,
    ]);

    $proseRow = $proseStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($proseRow)) {
        throw new RuntimeException(
            'No primary timeline-view prose projection attached to calendar event: ' . $entityId
        );
    }

    $proseBody = trim((string)($proseRow['prose_body'] ?? ''));

    if ($proseBody === '') {
        throw new RuntimeException(
            'Attached prose draft has empty prose_body'
        );
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
    $derivedBeat = trim((string)($metadata['beat_summary'] ?? ''));

    if ($derivationMode !== 'semantic_rule') {
        throw new RuntimeException(
            'Semantic beat/title derivation did not resolve for calendar event: ' . $entityId
        );
    }

    if ($derivedTitle === '') {
        throw new RuntimeException(
            'Unable to derive semantic calendar event title from attached prose'
        );
    }

    if ($derivedBeat === '') {
        throw new RuntimeException(
            'Unable to derive semantic calendar event beat from attached prose'
        );
    }

    $appliedMetadata = apply_calendar_event_metadata(
        $pdo,
        $entityId,
        $derivedTitle,
        $derivedBeat
    );

    $characterSuggestionHandoff = [
        'recommended_next_workflow_family' => 'semantic_suggestions',
        'recommended_next_workflow' => 'calendar_event_suggest_characters',
        'recommended_next_user_action' => 'Tag Characters from the attached prose.',
        'suggestion_target' => [
            'target_entity_type' => 'calendar_event',
            'target_entity_id' => $entityId,
            'source_entity_type' => 'prose_draft',
            'source_entity_id' => (string)$proseRow['prose_entity_id'],
            'source_projection_id' => (int)$proseRow['prose_projection_id'],
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
                        'entity_id' => 'character:shay_vertue',
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
                    'prose_entity_id' => (string)$proseRow['prose_entity_id'],
                    'prose_projection_id' => (int)$proseRow['prose_projection_id'],
                    'derived_title' => $derivedTitle,
                    'derived_beat' => $derivedBeat,
                    'derivation_mode' => $derivationMode,
                    'evidence' => $metadata['evidence'] ?? [],
                    'applied_calendar_event_metadata' => $appliedMetadata,
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
                        'prose_entity_id' => (string)$proseRow['prose_entity_id'],
                        'prose_projection_id' => (int)$proseRow['prose_projection_id'],
                    ],
                    'derived' => [
                        'title' => $derivedTitle,
                        'beat' => $derivedBeat,
                    ],
                    'apply_state' => [
                        'persisted' => true,
                        'topology_generated' => false,
                        'updated_rows' => $appliedMetadata['updated_rows'] ?? null,
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
