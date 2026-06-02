<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__ . '/workflow_sql_dropin_builder.php';
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

    $bookLabel = trim((string)(
        $context['projection']['entity_id']
        ?? $context['projection']['label']
        ?? ('projection_id:' . $projectionId)
    ));

    $authorHandoffParagraph = sprintf(
        'Continue from canonical Book event %s in %s, book_time_id %d, event index %d. This event already exists in the database and has been materialized. Use the stored event as the authority for Book event identity and canonical Book-time membership. Begin with beat design: structural event intent, scene function, character or system interactions, escalation logic, and author-facing choices before prose drafting. Semantic labels must come from canonical Book containers.',
        (string)$eventEntityId,
        $bookLabel,
        $bookTimeId,
        $resolvedEventIndex
    );

    $operatorSqlDropin = fw_workflow_calendar_book_event_sql_dropin(
        (int)($event['id'] ?? 0)
    );

    return [
        'success' => true,

        'context' => array_merge(
            $context,
            [
                'calendar_event' => $event,
                'projection_memberships' => $memberships,
                'projection_build_id' => $projectionBuildId,
                'operator_sql_dropin' => $operatorSqlDropin,

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

                        'use_existing_canonical_membership'
                            => true,

                        'begin_with_beat_design'
                            => true,
                    ],

                    'constraints' => [
                        'semantic_labels_must_come_from_canonical_containers',
                        'event_creation_is_ontology_first_not_prose_first',
                        'beat_generation_precedes_prose_authoring',
                    ],
                ],

                'calendar_book_event_create' => [
                    'projection_id' => $projectionId,
                    'book_time_id' => $bookTimeId,
                    'event_index' => $resolvedEventIndex,
                    'entity_id' => $eventEntityId,
                    'id' => $event['id'] ?? null,
                    'projection_build_id' => $projectionBuildId,
                ],
            ]
        ),
    ];
}
