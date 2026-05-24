<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__ . '/../calendar/calendar_week_prose_view_service.php';

function fw_execute_workflow_calendar_display_week_prose(
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

    $weekInput = trim((string)(
        $payload['week']
        ?? $payload['week_index']
        ?? ''
    ));

    if ($weekInput === '') {
        return [
            'success' => false,
            'error' => 'Missing canonical Book week input',
            'context' => $context,
        ];
    }

    $weekIndex = fw_normalize_calendar_index_value(
        $weekInput,
        'week'
    );

    $projectionId = (int)(
        $payload['projection_id']
        ?? $context['projection']['id']
        ?? 1
    );

    if ($projectionId < 1) {
        return [
            'success' => false,
            'error' => 'Unable to resolve canonical Book projection',
            'context' => $context,
        ];
    }

    $proseMode = normalize_calendar_week_prose_mode(
        $payload['prose_mode']
        ?? 'export'
    );

    $weekTree = resolve_calendar_week_prose_view(
        $pdo,
        $projectionId,
        $weekIndex,
        $proseMode
    );

    if ($weekTree === null) {
        return [
            'success' => false,
            'error' => 'Canonical Book week not found',
            'context' => $context,
        ];
    }

    $artifact = render_calendar_week_prose_artifact(
        $weekTree
    );

    return [
        'success' => true,

        'status' => 'ok',

        'workflow'
            => 'calendar_week_display_prose',

        'tier' => 1,

        'projection_id'
            => $projectionId,

        'week_index'
            => $weekIndex,

        'prose_mode'
            => $proseMode,

        'context' => array_merge(
            $context,
            [
                'artifact' => $artifact,
            ]
        ),
    ];
}
