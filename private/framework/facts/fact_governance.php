<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Canonical Governance Defaults
|--------------------------------------------------------------------------
|
| This file is the single authority for framework governance defaults.
| Runtime code should never hardcode governance ontology IDs directly.
|
| Governance semantics:
|
| - epistemic origin
| - adjudication status
| - contradiction state
|
| must resolve through this layer.
|
*/

const FACT_GOVERNANCE_DEFAULTS = [

    /*
    |--------------------------------------------------------------------------
    | Epistemic Origin
    |--------------------------------------------------------------------------
    */

    'epistemic_origin_classval_id'
    => 'epistemic_origin_asserted',

    /*
    |--------------------------------------------------------------------------
    | Adjudication Status
    |--------------------------------------------------------------------------
    */

    'adjudication_status_classval_id'
    => 'adjudication_status_accepted',

    /*
    |--------------------------------------------------------------------------
    | Contradiction State
    |--------------------------------------------------------------------------
    */

    'contradiction_state_classval_id'
    => 'contradiction_state_unassessed',
];

/*
|--------------------------------------------------------------------------
| Typed Governance Accessors
|--------------------------------------------------------------------------
*/

function governance_default_epistemic_origin(): string
{
    return FACT_GOVERNANCE_DEFAULTS[
    'epistemic_origin_classval_id'
    ];
}

function governance_default_adjudication_status(): string
{
    return FACT_GOVERNANCE_DEFAULTS[
    'adjudication_status_classval_id'
    ];
}

function governance_default_contradiction_state(): string
{
    return FACT_GOVERNANCE_DEFAULTS[
    'contradiction_state_classval_id'
    ];
}

/*
|--------------------------------------------------------------------------
| Governance Profiles
|--------------------------------------------------------------------------
|
| Profiles are the stable abstraction boundary for callers.
| Future ingress policies can evolve here without changing callers.
|
*/

function governance_profile_default(): array
{
    return [
        'epistemic_origin_classval_id'
        => governance_default_epistemic_origin(),

        'adjudication_status_classval_id'
        => governance_default_adjudication_status(),

        'contradiction_state_classval_id'
        => governance_default_contradiction_state(),
    ];
}

/*
|--------------------------------------------------------------------------
| Governance Resolution
|--------------------------------------------------------------------------
*/

function default_fact_governance(): array
{
    return governance_profile_default();
}

function resolve_fact_governance(
    ?array $governance = null
): array {
    $resolved = array_merge(
        governance_profile_default(),
        $governance ?? []
    );

    assert_valid_fact_governance($resolved);

    return $resolved;
}

/*
|--------------------------------------------------------------------------
| Governance Validation
|--------------------------------------------------------------------------
*/

function assert_valid_fact_governance(
    array $governance
): void {
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