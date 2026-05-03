<?php

/*
 * STRUCTURAL VALIDATOR ONLY
 *
 * This function enforces calendar tree shape using layer_id.
 * It must not be used to infer or validate semantic meaning.
 *
 * Semantic authority belongs to entities.entity_type_id
 * and must be enforced separately.
 */
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

function assert_calendar_semantic_parent_child(
    string $parentEntityTypeId,
    string $childEntityTypeId
): void {
    $allowed = [
        'entity_type_calendar_time'  => ['entity_type_calendar_event'],
        'entity_type_calendar_event' => ['entity_type_calendar_event'], // subevent
    ];

    if (!array_key_exists($parentEntityTypeId, $allowed)) {
        throw new RuntimeException(
            'Invalid parent entity type for calendar hierarchy: ' . $parentEntityTypeId
        );
    }

    if (!in_array($childEntityTypeId, $allowed[$parentEntityTypeId], true)) {
        throw new RuntimeException(
            'Invalid child type ' . $childEntityTypeId .
            ' for parent type ' . $parentEntityTypeId
        );
    }
}