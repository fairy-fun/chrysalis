<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';
require_once __DIR__ . '/../calendar/calendar_week_prose_view_service.php';

function fw_execute_workflow_calendar_display_week_day_prose(
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

    $dayInput = trim((string)(
        $payload['day']
        ?? $payload['day_index']
        ?? ''
    ));

    if ($weekInput === '') {
        return [
            'success' => false,
            'error' => 'Missing canonical Book week input',
            'context' => $context,
        ];
    }

    if ($dayInput === '') {
        return [
            'success' => false,
            'error' => 'Missing canonical Book day input',
            'context' => $context,
        ];
    }

    $weekIndex = fw_normalize_calendar_index_value(
        $weekInput,
        'week'
    );

    $dayIndex = fw_normalize_calendar_index_value(
        $dayInput,
        'day'
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

    $weekTree = resolve_calendar_week_prose_view(
        $pdo,
        $projectionId,
        $weekIndex
    );

    if ($weekTree === null) {
        return [
            'success' => false,
            'error' => 'Canonical Book week not found',
            'context' => $context,
        ];
    }

    $artifact = render_calendar_week_prose_artifact(
        $weekTree,
        $dayIndex
    );

    return [
        'success' => true,

        'status' => 'ok',

        'workflow'
            => 'calendar_week_day_display_prose',

        'tier' => 1,

        'projection_id'
            => $projectionId,

        'week_index'
            => $weekIndex,

        'day_index'
            => $dayIndex,

        'context' => array_merge(
            $context,
            [
                'artifact' => $artifact,
            ]
        ),
    ];
}
