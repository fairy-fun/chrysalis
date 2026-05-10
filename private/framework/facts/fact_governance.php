<?php
declare(strict_types=1);

function default_fact_governance(): array
{
    return [
        'epistemic_origin_classval_id' => 'epistemic_origin_observed',
        'adjudication_status_classval_id' => 'adjudication_status_unreviewed',
        'contradiction_state_classval_id' => 'contradiction_state_unassessed',
    ];
}

function resolve_fact_governance(?array $governance = null): array
{
    return array_merge(default_fact_governance(), $governance ?? []);
}