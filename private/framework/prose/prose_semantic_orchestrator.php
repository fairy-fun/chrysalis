<?php

declare(strict_types=1);

require_once __DIR__ . '/story_surface_resolver.php';

require_once __DIR__ . '/../calendar/calendar_subevent_reconciler.php';

/**
 * Semantic prose orchestration layer.
 *
 * This layer is orchestration only.
 */
function orchestrate_prose_semantics(
    PDO $pdo,
    array $parentEvent,
    string $prose,
    array $options = []
): array {

    $parentEntityId = trim((string)($parentEvent['entity_id'] ?? ''));

    if ($parentEntityId === '') {
        throw new InvalidArgumentException('Missing parent event entity_id');
    }

    $projectionId = isset($parentEvent['projection_id'])
        ? (int)$parentEvent['projection_id']
        : null;

    if ($projectionId === null || $projectionId < 1) {
        throw new InvalidArgumentException('Missing parent projection_id');
    }

    $prose = trim($prose);

    if ($prose === '') {
        throw new InvalidArgumentException('Prose body is required');
    }

    $reconciliation = reconcile_calendar_subevents(
        $pdo,
        $parentEvent,
        $prose,
        $options
    );

    $chronologyAddress = trim(
        (string)($parentEvent['chronology_address'] ?? '')
    );

    $surface = null;

    if ($chronologyAddress !== '') {
        $surface = resolve_story_surface(
            $pdo,
            $projectionId,
            $chronologyAddress
        );
    }

    return [
        'status' => 'ok',

        'semantic_summary' => [
            'segment_count' => (int)(
                $reconciliation['summary']['desired_count'] ?? 0
            ),
            'reconciled_count' => (int)(
                $reconciliation['summary']['reconciled_count'] ?? 0
            ),
            'orphaned_count' => (int)(
                $reconciliation['summary']['orphaned_count'] ?? 0
            ),
        ],

        'parent_event' => [
            'entity_id' => $parentEntityId,
            'projection_id' => $projectionId,
            'chronology_address' => $chronologyAddress !== ''
                ? $chronologyAddress
                : null,
        ],

        'reconciliation' => $reconciliation,
        'story_surface' => $surface,
    ];
}