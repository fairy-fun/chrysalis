<?php

declare(strict_types=1);

require_once __DIR__ . '/fact_governance.php';

/**
 * Resolve canonical global fact.
 *
 * Canonical fact =
 * fact row that is NOT superseded by another row.
 *
 * When $forUpdate is true, this must be called inside a transaction.
 */
function resolve_canonical_global_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $factTypeId,
    ?string $objectEntityId = null,
    bool $acceptedOnly = false,
    bool $forUpdate = false
): ?array {

    if ($subjectEntityId === '' || $factTypeId === '') {
        throw new InvalidArgumentException(
            'subjectEntityId and factTypeId are required'
        );
    }

    if ($forUpdate && !$pdo->inTransaction()) {
        throw new RuntimeException(
            'FOR UPDATE canonical fact resolution requires an active transaction'
        );
    }

    $acceptedClause = $acceptedOnly
        ? 'AND ' .
        governance_filter_accepted_adjudication_sql(
            'f.adjudication_status_classval_id'
        )
        : '';

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
        : '';

    $forUpdateClause = $forUpdate
        ? 'FOR UPDATE'
        : '';

    $sql = <<<SQL
SELECT f.*
FROM entity_linked_facts_global f
LEFT JOIN entity_linked_facts_global newer
    ON newer.supersedes_linked_fact_id = f.linked_fact_id
WHERE newer.linked_fact_id IS NULL
  AND f.subject_entity_id = :subject
  AND f.fact_type_id = :fact_type
  {$objectClause}
  {$acceptedClause}
LIMIT 2
{$forUpdateClause}
SQL;

    $stmt = $pdo->prepare($sql);

    $params = [
        'subject' => $subjectEntityId,
        'fact_type' => $factTypeId,
    ];

    if ($objectEntityId !== null) {
        $params['object'] = $objectEntityId;
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple current global facts found for canonical slot'
        );
    }

    return $rows[0] ?? null;
}

/**
 * Resolve canonical event fact.
 *
 * Canonical fact =
 * fact row that is NOT superseded by another row.
 *
 * When $forUpdate is true, this must be called inside a transaction.
 */
function resolve_canonical_event_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $contextEntityId,
    string $factTypeId,
    ?string $objectEntityId = null,
    bool $acceptedOnly = false,
    bool $forUpdate = false
): ?array {

    if (
        $subjectEntityId === '' ||
        $contextEntityId === '' ||
        $factTypeId === ''
    ) {
        throw new InvalidArgumentException(
            'subjectEntityId, contextEntityId, and factTypeId are required'
        );
    }

    if ($forUpdate && !$pdo->inTransaction()) {
        throw new RuntimeException(
            'FOR UPDATE canonical fact resolution requires an active transaction'
        );
    }

    $acceptedClause = $acceptedOnly
        ? 'AND ' .
        governance_filter_accepted_adjudication_sql(
            'f.adjudication_status_classval_id'
        )
        : '';

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
        : '';

    $forUpdateClause = $forUpdate
        ? 'FOR UPDATE'
        : '';

    $sql = <<<SQL
SELECT f.*
FROM entity_linked_facts_event f
LEFT JOIN entity_linked_facts_event newer
    ON newer.supersedes_linked_fact_id = f.linked_fact_id
WHERE newer.linked_fact_id IS NULL
  AND f.subject_entity_id = :subject
  AND f.context_entity_id = :context
  AND f.fact_type_id = :fact_type
  {$objectClause}
  {$acceptedClause}
LIMIT 2
{$forUpdateClause}
SQL;

    $stmt = $pdo->prepare($sql);

    $params = [
        'subject' => $subjectEntityId,
        'context' => $contextEntityId,
        'fact_type' => $factTypeId,
    ];

    if ($objectEntityId !== null) {
        $params['object'] = $objectEntityId;
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple current event facts found for canonical slot'
        );
    }

    return $rows[0] ?? null;
}