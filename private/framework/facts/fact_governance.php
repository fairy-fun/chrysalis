<?php

declare(strict_types=1);

require_once __DIR__ . '/../classval/governance_domains.php';
require_once __DIR__ . '/../classval/governance_codes.php';
require_once __DIR__ . '/../classval/governance_classvals.php';
require_once __DIR__ . '/../classvals/classval_resolver.php';

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

    assert_valid_fact_governance($pdo, $resolved);

    return $resolved;
}

/*
|--------------------------------------------------------------------------
| Governance Validation
|--------------------------------------------------------------------------
*/

function assert_governance_classval_membership(
    PDO $pdo,
    string $classvalId,
    string $expectedTypeCode
): void {
    $stmt = $pdo->prepare(<<<SQL
SELECT t.code AS type_code
FROM classvals cv
JOIN classval_type_classvals t
  ON t.id = cv.classval_type_id
WHERE cv.id = :id
LIMIT 1
SQL);

    $stmt->execute([
        'id' => $classvalId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        throw new RuntimeException(
            'Governance classval does not exist: '
            . $classvalId
        );
    }

    if ($row['type_code'] !== $expectedTypeCode) {
        throw new RuntimeException(
            'Governance classval ontology mismatch. '
            . 'Expected '
            . $expectedTypeCode
            . ', received '
            . $row['type_code']
        );
    }
}

function assert_valid_fact_governance(
    PDO $pdo,
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

    assert_governance_classval_membership(
        $pdo,
        $governance['epistemic_origin_classval_id'],
        GovernanceDomains::EPISTEMIC_ORIGIN
    );

    assert_governance_classval_membership(
        $pdo,
        $governance['adjudication_status_classval_id'],
        GovernanceDomains::ADJUDICATION_STATUS
    );

    assert_governance_classval_membership(
        $pdo,
        $governance['contradiction_state_classval_id'],
        GovernanceDomains::CONTRADICTION_STATE
    );
}

function governance_accepted_adjudication_id(
    PDO $pdo
): string {
    return governance_default_adjudication_status($pdo);
}
