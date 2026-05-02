<?php
function assert_calendar_parent_transition(
    ?array $parent,
    string $childLayerId
): void {
    $allowed = [
        'calendar_layer_week'  => null,
        'calendar_layer_day'   => 'calendar_layer_week',
        'calendar_layer_time'  => 'calendar_layer_day',
        'calendar_layer_event' => 'calendar_layer_time',
        'calendar_layer_subevent' => 'calendar_layer_event',
    ];

    if (!array_key_exists($childLayerId, $allowed)) {
        throw new InvalidArgumentException('Unknown calendar layer: ' . $childLayerId);
    }

    $requiredParentLayer = $allowed[$childLayerId];

    if ($requiredParentLayer === null) {
        if ($parent !== null) {
            throw new RuntimeException('calendar_layer_week must not have a parent');
        }
        return;
    }

    if ($parent === null) {
        throw new RuntimeException($childLayerId . ' requires a parent');
    }

    if (($parent['layer_id'] ?? null) !== $requiredParentLayer) {
        throw new RuntimeException(
            $childLayerId . ' parent must be ' . $requiredParentLayer .
            '; got ' . (($parent['layer_id'] ?? null) ?: 'NULL')
        );
    }
}