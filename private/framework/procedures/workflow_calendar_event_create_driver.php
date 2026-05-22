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

    return [
        'success' => true,

        'context' => array_merge(
            $context,
            [
                'calendar_event' => $event,
                'projection_memberships' => $memberships,
                'projection_build_id' => $projectionBuildId,

                'handoff_packet' => [
                    'workflow_stage' => 'event_shell_created',

                    'canonical' => [
                        'calendar_event_entity_id'
                            => $event['entity_id'] ?? null,

                        'projection_id'
                            => $projectionId,

                        'book_time_id'
                            => $bookTimeId,

                        'event_index'
                            => $resolvedEventIndex,
                    ],

                    'next_workflow' => [
                        'workflow_id'
                            => 'calendar_event_add_prose',

                        'initial_context' => [
                            'calendar_event_entity_id'
                                => $event['entity_id'] ?? null,
                        ],
                    ],
                ],

                'calendar_book_event_create' => [
                    'projection_id' => $projectionId,
                    'book_time_id' => $bookTimeId,
                    'event_index' => $resolvedEventIndex,
                    'entity_id' => $event['entity_id'] ?? null,
                    'id' => $event['id'] ?? null,
                    'projection_build_id' => $projectionBuildId,
                ],
            ]
        ),
    ];
}
