<?php

declare(strict_types=1);

require_once __DIR__ . '/fact_governance.php';

/*
|--------------------------------------------------------------------------
| Temporal Fact Reconstruction
|--------------------------------------------------------------------------
|
| Temporal doctrine:
|
| - facts are immutable
| - supersession defines lineage
| - lineage is acyclic
| - canonicality is computed
| - temporal state is reconstructed
| - created_at is semantic infrastructure
|
| A fact is canonical at time T iff:
|
| - the fact existed at T
| - no successor existed at T
|
| Runtime assumes lineage integrity, acyclic supersession, and monotonic
| temporal ordering. CI audits enforce those invariants globally.
|
*/

function assert_valid_fact_as_of(
    string $asOf
): void {

    if (trim($asOf) === '') {
        throw new InvalidArgumentException(
            'asOf timestamp is required'
        );
    }

    $formats = [
        'Y-m-d H:i:s',
        DATE_ATOM,
    ];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat(
            $format,
            $asOf
        );

        if ($dt === false) {
            continue;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $errors === false
            || (
                $errors['warning_count'] === 0
                && $errors['error_count'] === 0
            )
        ) {
            return;
        }
    }

    throw new InvalidArgumentException(
        'Invalid asOf timestamp format'
    );
}

function temporal_accepted_clause(
    bool $acceptedOnly,
    string $alias = 'f'
): string {

    return $acceptedOnly
        ? "AND {$alias}.adjudication_status_classval_id = :accepted"
        : '';
}

function temporal_object_clause(
    ?string $objectEntityId,
    string $alias = 'f'
): string {

    return $objectEntityId !== null
        ? "AND {$alias}.object_entity_id = :object"
        : '';
}

/*
|--------------------------------------------------------------------------
| Slot-Based Temporal Reconstruction
|--------------------------------------------------------------------------
*/

function resolve_global_fact_at_time(
    PDO $pdo,
    string $subjectEntityId,
    string $factTypeId,
    ?string $objectEntityId,
    string $asOf,
    bool $acceptedOnly = false
): ?array {

    if (
        $subjectEntityId === ''
        || $factTypeId === ''
    ) {
        throw new InvalidArgumentException(
            'subjectEntityId and factTypeId are required'
        );
    }

    assert_valid_fact_as_of($asOf);

    $objectClause =
        temporal_object_clause($objectEntityId);

    $acceptedClause =
        temporal_accepted_clause($acceptedOnly);

    $sql = <<<SQL
SELECT f.*
FROM entity_linked_facts_global f
WHERE f.subject_entity_id = :subject
  AND f.fact_type_id = :fact_type
  {$objectClause}
  {$acceptedClause}
  AND f.created_at <= :as_of
  AND NOT EXISTS (
      SELECT 1
      FROM entity_linked_facts_global newer
      WHERE newer.supersedes_linked_fact_id =
            f.linked_fact_id
        AND newer.created_at <= :as_of
  )
ORDER BY
    f.created_at DESC,
    f.linked_fact_id DESC
LIMIT 2
SQL;

    $stmt = $pdo->prepare($sql);

    $params = [
        'subject' => $subjectEntityId,
        'fact_type' => $factTypeId,
        'as_of' => $asOf,
    ];

    if ($objectEntityId !== null) {
        $params['object'] = $objectEntityId;
    }

    if ($acceptedOnly) {
        $params['accepted'] =
            governance_accepted_adjudication_id($pdo);
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple canonical global facts found '
            . 'for historical slot reconstruction'
        );
    }

    return $rows[0] ?? null;
}

function resolve_event_fact_at_time(
    PDO $pdo,
    string $subjectEntityId,
    string $contextEntityId,
    string $factTypeId,
    ?string $objectEntityId,
    string $asOf,
    bool $acceptedOnly = false
): ?array {

    if (
        $subjectEntityId === ''
        || $contextEntityId === ''
        || $factTypeId === ''
    ) {
        throw new InvalidArgumentException(
            'subjectEntityId, contextEntityId, '
            . 'and factTypeId are required'
        );
    }

    assert_valid_fact_as_of($asOf);

    $objectClause =
        temporal_object_clause($objectEntityId);

    $acceptedClause =
        temporal_accepted_clause($acceptedOnly);

    $sql = <<<SQL
SELECT f.*
FROM entity_linked_facts_event f
WHERE f.subject_entity_id = :subject
  AND f.context_entity_id = :context
  AND f.fact_type_id = :fact_type
  {$objectClause}
  {$acceptedClause}
  AND f.created_at <= :as_of
  AND NOT EXISTS (
      SELECT 1
      FROM entity_linked_facts_event newer
      WHERE newer.supersedes_linked_fact_id =
            f.linked_fact_id
        AND newer.created_at <= :as_of
  )
ORDER BY
    f.created_at DESC,
    f.linked_fact_id DESC
LIMIT 2
SQL;

    $stmt = $pdo->prepare($sql);

    $params = [
        'subject' => $subjectEntityId,
        'context' => $contextEntityId,
        'fact_type' => $factTypeId,
        'as_of' => $asOf,
    ];

    if ($objectEntityId !== null) {
        $params['object'] = $objectEntityId;
    }

    if ($acceptedOnly) {
        $params['accepted'] =
            governance_accepted_adjudication_id($pdo);
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple canonical event facts found '
            . 'for historical slot reconstruction'
        );
    }

    return $rows[0] ?? null;
}

/*
|--------------------------------------------------------------------------
| Temporal Lineage Reconstruction
|--------------------------------------------------------------------------
*/

function resolve_global_fact_head_at_time_by_linked_fact_id(
    PDO $pdo,
    int $linkedFactId,
    string $asOf,
    bool $acceptedOnly = false
): ?array {

    if ($linkedFactId < 1) {
        throw new InvalidArgumentException(
            'linkedFactId must be positive'
        );
    }

    assert_valid_fact_as_of($asOf);

    $acceptedClause =
        temporal_accepted_clause(
            $acceptedOnly,
            'lineage'
        );

    $sql = <<<SQL
WITH RECURSIVE lineage AS (

    SELECT *
    FROM entity_linked_facts_global
    WHERE linked_fact_id = :linked_fact_id

    UNION ALL

    SELECT newer.*
    FROM entity_linked_facts_global newer
    JOIN lineage l
      ON newer.supersedes_linked_fact_id =
         l.linked_fact_id
)
SELECT lineage.*
FROM lineage
WHERE lineage.created_at <= :as_of
  {$acceptedClause}
  AND NOT EXISTS (
      SELECT 1
      FROM entity_linked_facts_global newer
      WHERE newer.supersedes_linked_fact_id =
            lineage.linked_fact_id
        AND newer.created_at <= :as_of
  )
ORDER BY
    lineage.created_at DESC,
    lineage.linked_fact_id DESC
LIMIT 2
SQL;

    $stmt = $pdo->prepare($sql);

    $params = [
        'linked_fact_id' => $linkedFactId,
        'as_of' => $asOf,
    ];

    if ($acceptedOnly) {
        $params['accepted'] =
            governance_accepted_adjudication_id($pdo);
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple historical global lineage heads detected'
        );
    }

    return $rows[0] ?? null;
}

function resolve_event_fact_head_at_time_by_linked_fact_id(
    PDO $pdo,
    int $linkedFactId,
    string $asOf,
    bool $acceptedOnly = false
): ?array {

    if ($linkedFactId < 1) {
        throw new InvalidArgumentException(
            'linkedFactId must be positive'
        );
    }

    assert_valid_fact_as_of($asOf);

    $acceptedClause =
        temporal_accepted_clause(
            $acceptedOnly,
            'lineage'
        );

    $sql = <<<SQL
WITH RECURSIVE lineage AS (

    SELECT *
    FROM entity_linked_facts_event
    WHERE linked_fact_id = :linked_fact_id

    UNION ALL

    SELECT newer.*
    FROM entity_linked_facts_event newer
    JOIN lineage l
      ON newer.supersedes_linked_fact_id =
         l.linked_fact_id
)
SELECT lineage.*
FROM lineage
WHERE lineage.created_at <= :as_of
  {$acceptedClause}
  AND NOT EXISTS (
      SELECT 1
      FROM entity_linked_facts_event newer
      WHERE newer.supersedes_linked_fact_id =
            lineage.linked_fact_id
        AND newer.created_at <= :as_of
  )
ORDER BY
    lineage.created_at DESC,
    lineage.linked_fact_id DESC
LIMIT 2
SQL;

    $stmt = $pdo->prepare($sql);

    $params = [
        'linked_fact_id' => $linkedFactId,
        'as_of' => $asOf,
    ];

    if ($acceptedOnly) {
        $params['accepted'] =
            governance_accepted_adjudication_id($pdo);
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple historical event lineage heads detected'
        );
    }

    return $rows[0] ?? null;
}