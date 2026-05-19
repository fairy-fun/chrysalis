<?php

declare(strict_types=1);

function build_calendar_subevent_artifact_graph(
    string $parentEventEntityId,
    array $executionResult
): array {

    $results = $executionResult['results'] ?? [];

    $subevents = [];

    foreach ($results as $row) {

        $subevents[] = [
            'slot' => $row['event']['subevent_index'] ?? null,
            'entity_id' => $row['event']['entity_id'] ?? null,
            'state' => ($row['idempotent'] ?? false)
                ? 'existing'
                : 'created',
        ];
    }

    return [
        'parent_event_entity_id' => $parentEventEntityId,
        'subevent_count' => $executionResult['subevent_count'] ?? 0,
        'persisted_count' => $executionResult['persisted_count'] ?? 0,
        'subevents' => $subevents,
    ];
}