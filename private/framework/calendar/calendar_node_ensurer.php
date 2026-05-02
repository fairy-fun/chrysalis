<?php

function assert_calendar_parent_transition(
    ?array $parent,
    string $childEntityTypeId
): void {
    $allowed = [
        'entity_type_calendar_week'     => null,
        'entity_type_calendar_day'      => 'entity_type_calendar_week',
        'entity_type_calendar_time'     => 'entity_type_calendar_day',
        'entity_type_calendar_event'    => 'entity_type_calendar_time',
        'entity_type_calendar_subevent' => 'entity_type_calendar_event',
    ];

    if (!array_key_exists($childEntityTypeId, $allowed)) {
        throw new InvalidArgumentException('Unknown calendar entity type: ' . $childEntityTypeId);
    }

    $requiredParentType = $allowed[$childEntityTypeId];

    if ($requiredParentType === null) {
        if ($parent !== null) {
            throw new RuntimeException('entity_type_calendar_week must not have a parent');
        }
        return;
    }

    if ($parent === null) {
        throw new RuntimeException($childEntityTypeId . ' requires a parent');
    }

    // ✅ Authoritative semantic check
    if (($parent['entity_type_id'] ?? null) !== $requiredParentType) {
        throw new RuntimeException(
            $childEntityTypeId . ' parent must be ' . $requiredParentType .
            '; got ' . (($parent['entity_type_id'] ?? null) ?: 'NULL')
        );
    }

    // ⚠️ Transitional structural assertion (Phase 14)
    $layerMap = [
        'entity_type_calendar_week'     => 'calendar_layer_week',
        'entity_type_calendar_day'      => 'calendar_layer_day',
        'entity_type_calendar_time'     => 'calendar_layer_time',
        'entity_type_calendar_event'    => 'calendar_layer_event',
        'entity_type_calendar_subevent' => 'calendar_layer_subevent',
    ];

    if (isset($layerMap[$requiredParentType])) {
        $expectedLayer = $layerMap[$requiredParentType];

        if (($parent['layer_id'] ?? null) !== $expectedLayer) {
            throw new RuntimeException(
                'Parent layer_id mismatch for ' . $requiredParentType .
                '; expected ' . $expectedLayer .
                ', got ' . (($parent['layer_id'] ?? null) ?: 'NULL')
            );
        }
    }
}