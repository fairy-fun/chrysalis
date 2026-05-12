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





/*
|--------------------------------------------------------------------------
| Typed Governance Accessors
|--------------------------------------------------------------------------
*/

    /*
    |--------------------------------------------------------------------------
    | Epistemic Origin
    |--------------------------------------------------------------------------
    */
function governance_default_epistemic_origin(PDO $pdo): string
{
    return governance_classval_id(
        $pdo,
        GovernanceDomains::EPISTEMIC_ORIGIN,
        GovernanceCodes::EPISTEMIC_ASSERTED
    );
}


/*
    |--------------------------------------------------------------------------
    | Adjudication Status
    |--------------------------------------------------------------------------
    */

function governance_default_adjudication_status(PDO $pdo): string
{
    return governance_classval_id(
        $pdo,
        GovernanceDomains::ADJUDICATION_STATUS,
        GovernanceCodes::ADJUDICATION_ACCEPTED
    );
}


/*
    |--------------------------------------------------------------------------
    | Contradiction State
    |--------------------------------------------------------------------------
    */

function governance_default_contradiction_state(PDO $pdo): string
{
    return governance_classval_id(
        $pdo,
        GovernanceDomains::CONTRADICTION_STATE,
        GovernanceCodes::CONTRADICTION_UNASSESSED
    );
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

function governance_profile_default(PDO $pdo): array
{
    return [
        'epistemic_origin_classval_id'
        => governance_default_epistemic_origin($pdo),

        'adjudication_status_classval_id'
        => governance_default_adjudication_status($pdo),

        'contradiction_state_classval_id'
        => governance_default_contradiction_state($pdo),
    ];
}


/*
|--------------------------------------------------------------------------
| Governance Resolution
|--------------------------------------------------------------------------
*/

function default_fact_governance(PDO $pdo): array
{
    return governance_profile_default($pdo);
}

function resolve_fact_governance(
    PDO $pdo,
    ?array $governance = null
): array {
    $resolved = array_merge(
        governance_profile_default($pdo),
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

function governance_accepted_adjudication_id(
    PDO $pdo
): string {
    return governance_default_adjudication_status($pdo);
}