<?php
declare(strict_types=1);

function default_fact_governance(): array
{
    return [
        'epistemic_origin_classval_id'
        => '47ff0741-4c9a-11f1-80c0-fa163e40c938',

        'adjudication_status_classval_id'
        => '4807d536-4c9a-11f1-80c0-fa163e40c938',

        // still canonical in current ontology
        'contradiction_state_classval_id'
        => 'contradiction_state_unassessed',
    ];
}

function resolve_fact_governance(?array $governance = null): array
{
    $resolved = array_merge(
        default_fact_governance(),
        $governance ?? []
    );

    assert_valid_fact_governance($resolved);

    return $resolved;
}

function assert_valid_fact_governance(array $governance): void
{
    $required = [
        'epistemic_origin_classval_id',
        'adjudication_status_classval_id',
        'contradiction_state_classval_id',
    ];

    foreach ($required as $field) {
        if (!array_key_exists($field, $governance)) {
            throw new InvalidArgumentException(
                'Missing governance field: ' . $field
            );
        }

        if (
            !is_string($governance[$field]) ||
            trim($governance[$field]) === ''
        ) {
            throw new InvalidArgumentException(
                'Invalid governance field: ' . $field
            );
        }
    }
}