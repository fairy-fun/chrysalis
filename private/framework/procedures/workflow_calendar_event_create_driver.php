<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__ . '/../calendar/calendar_book_event_ensurer.php';
require_once __DIR__ . '/../calendar/calendar_event_projection_membership_service.php';
require_once __DIR__ . '/../calendar/calendar_projection_materializer.php';

function fw_execute_workflow_calendar_create_book_event(
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

    $projectionId = (int)($payload['projection_id'] ?? 0);
    $bookTimeId = (int)($payload['book_time_id'] ?? 0);

    $eventIndex = null;

    if (
        array_key_exists('event_index', $payload)
        && $payload['event_index'] !== null
        && $payload['event_index'] !== ''
    ) {
        $eventIndex = (int)$payload['event_index'];
    }

    $summary = trim((string)($payload['summary'] ?? ''));

    if ($projectionId < 1) {
        throw new RuntimeException(
            'create_book_event requires projection_id'
        );
    }

    if ($bookTimeId < 1) {
        throw new RuntimeException(
            'create_book_event requires book_time_id'
        );
    }

    if ($eventIndex !== null && $eventIndex < 1) {
        throw new RuntimeException(
            'event_index must be positive'
        );
    }

    if ($summary === '') {
        throw new RuntimeException(
            'create_book_event requires summary'
        );
    }

    $event = ensure_calendar_book_event(
        $pdo,
        $bookTimeId,
        $eventIndex,
        [
            'summary' => $summary,
        ]
    );

    $memberships = ensure_calendar_event_projection_memberships(
        $pdo,
        (int)$event['id'],
        [$projectionId]
    );

    $projectionBuildId = rebuild_calendar_projection(
        $pdo,
        $projectionId
    );

    $resolvedEventIndex =
        (int)($event['event_index'] ?? $eventIndex ?? 0);

    $eventEntityId = $event['entity_id'] ?? null;

    $weekIndex = (int)(
        $context['book_week']['week_index']
        ?? $context['calendar_normalized_input']['week_index']
        ?? 0
    );

    $dayIndex = (int)(
        $context['book_day']['day_index']
        ?? $context['calendar_normalized_input']['day_index']
        ?? 0
    );

    $timeIndex = (int)(
        $context['book_time']['time_index']
        ?? $context['calendar_normalized_input']['time_index']
        ?? 0
    );

    $timeLabel = trim((string)(
        $context['book_time']['display_label']
        ?? $context['book_time']['time_label']
        ?? $context['book_time']['summary']
        ?? ''
    ));

    if ($timeLabel === '') {
        $timeLabel = 'Time ' . $timeIndex;
    }

    $bookLabel = trim((string)(
        $context['projection']['entity_id']
        ?? $context['projection']['label']
        ?? ('projection_id:' . $projectionId)
    ));

    $localityLabel = sprintf(
        'Week %d, Day %d, %s',
        $weekIndex,
        $dayIndex,
        $timeLabel
    );

    $authorHandoffParagraph = sprintf(
        'Continue from canonical Book event %s in %s, %s, event index %d. This event already exists in the database and has been materialized. Use the stored event as the authority for Book, Week, Day, Time, and event identity. Begin with beat design: structural event intent, scene function, character or system interactions, escalation logic, and author-facing choices before prose drafting. chronology_address is render identity only, and semantic labels must come from canonical Book containers.',
        (string)$eventEntityId,
        $bookLabel,
        $localityLabel,
        $resolvedEventIndex
    );

    return [
        'success' => true,

        'context' => array_merge(
            $context,
            [
                'calendar_event' => $event,
                'projection_memberships' => $memberships,
                'projection_build_id' => $projectionBuildId,

                'handoff_packet' => [
                    'workflow_stage' => 'event_created',
                    'handoff_type' => 'beat_generation_author_handoff',

                    'canonical' => [
                        'calendar_event_entity_id'
                            => $eventEntityId,

                        'projection_id'
                            => $projectionId,

                        'book_time_id'
                            => $bookTimeId,

                        'week_index'
                            => $weekIndex,

                        'day_index'
                            => $dayIndex,

                        'time_index'
                            => $timeIndex,

                        'time_label'
                            => $timeLabel,

                        'event_index'
                            => $resolvedEventIndex,
                    ],

                    'author_handoff_paragraph'
                        => $authorHandoffParagraph,

                    'next_operator_handoff' => [
                        'target_entity_id'
                            => $eventEntityId,

                        'recommended_action'
                            => 'generate_beat_design_handoff',

                        'reuse_existing_event'
                            => true,

                        'use_existing_chronology_locality'
                            => true,

                        'begin_with_beat_design'
                            => true,
                    ],

                    'constraints' => [
                        'chronology_address_is_render_identity_only',
                        'semantic_labels_must_come_from_canonical_containers',
                        'event_creation_is_ontology_first_not_prose_first',
                        'beat_generation_precedes_prose_authoring',
                    ],
                ],

                'calendar_book_event_create' => [
                    'projection_id' => $projectionId,
                    'book_time_id' => $bookTimeId,
                    'week_index' => $weekIndex,
                    'day_index' => $dayIndex,
                    'time_index' => $timeIndex,
                    'time_label' => $timeLabel,
                    'event_index' => $resolvedEventIndex,
                    'entity_id' => $eventEntityId,
                    'id' => $event['id'] ?? null,
                    'projection_build_id' => $projectionBuildId,
                ],
            ]
        ),
    ];
}
