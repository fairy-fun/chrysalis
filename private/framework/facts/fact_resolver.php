<?php

declare(strict_types=1);

require_once __DIR__ . '/fact_governance.php';

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
        ? 'AND f.adjudication_status_classval_id = :accepted'
        : '';

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
        : '';

    $forUpdateClause = $forUpdate ? 'FOR UPDATE' : '';

    $sql = <<<SQL
SELECT f.*
FROM entity_linked_facts_global f
WHERE f.subject_entity_id = :subject
  AND f.fact_type_id = :fact_type
  {$objectClause}
  {$acceptedClause}
  AND NOT EXISTS (
      SELECT 1
      FROM entity_linked_facts_global newer
      WHERE newer.supersedes_linked_fact_id = f.linked_fact_id
  )
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

    if ($acceptedOnly) {
        $params['accepted'] =
            governance_accepted_adjudication_id($pdo);
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
        ? 'AND f.adjudication_status_classval_id = :accepted'
        : '';

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
        : '';

    $forUpdateClause = $forUpdate ? 'FOR UPDATE' : '';

    $sql = <<<SQL
SELECT f.*
FROM entity_linked_facts_event f
WHERE f.subject_entity_id = :subject
  AND f.context_entity_id = :context
  AND f.fact_type_id = :fact_type
  {$objectClause}
  {$acceptedClause}
  AND NOT EXISTS (
      SELECT 1
      FROM entity_linked_facts_event newer
      WHERE newer.supersedes_linked_fact_id = f.linked_fact_id
  )
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

    if ($acceptedOnly) {
        $params['accepted'] =
            governance_accepted_adjudication_id($pdo);
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

function resolve_canonical_global_fact_by_linked_fact_id(
    PDO $pdo,
    int $linkedFactId,
    bool $acceptedOnly = false,
    bool $forUpdate = false
): ?array {

    if ($linkedFactId < 1) {
        throw new InvalidArgumentException(
            'linkedFactId must be positive'
        );
    }

    if ($forUpdate && !$pdo->inTransaction()) {
        throw new RuntimeException(
            'FOR UPDATE canonical fact resolution requires an active transaction'
        );
    }

    $acceptedClause = $acceptedOnly
        ? 'AND lineage.adjudication_status_classval_id = :accepted'
        : '';

    $forUpdateClause = $forUpdate ? 'FOR UPDATE' : '';

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
WHERE NOT EXISTS (
    SELECT 1
    FROM entity_linked_facts_global newer
    WHERE newer.supersedes_linked_fact_id =
          lineage.linked_fact_id
)
{$acceptedClause}
LIMIT 2
{$forUpdateClause}
SQL;

    $stmt = $pdo->prepare($sql);

    $params = [
        'linked_fact_id' => $linkedFactId,
    ];

    if ($acceptedOnly) {
        $params['accepted'] =
            governance_accepted_adjudication_id($pdo);
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple canonical global lineage heads detected'
        );
    }

    return $rows[0] ?? null;
}

function resolve_canonical_event_fact_by_linked_fact_id(
    PDO $pdo,
    int $linkedFactId,
    bool $acceptedOnly = false,
    bool $forUpdate = false
): ?array {

    if ($linkedFactId < 1) {
        throw new InvalidArgumentException(
            'linkedFactId must be positive'
        );
    }

    if ($forUpdate && !$pdo->inTransaction()) {
        throw new RuntimeException(
            'FOR UPDATE canonical fact resolution requires an active transaction'
        );
    }

    $acceptedClause = $acceptedOnly
        ? 'AND lineage.adjudication_status_classval_id = :accepted'
        : '';

    $forUpdateClause = $forUpdate ? 'FOR UPDATE' : '';

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
WHERE NOT EXISTS (
    SELECT 1
    FROM entity_linked_facts_event newer
    WHERE newer.supersedes_linked_fact_id =
          lineage.linked_fact_id
)
{$acceptedClause}
LIMIT 2
{$forUpdateClause}
SQL;

    $stmt = $pdo->prepare($sql);

    $params = [
        'linked_fact_id' => $linkedFactId,
    ];

    if ($acceptedOnly) {
        $params['accepted'] =
            governance_accepted_adjudication_id($pdo);
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple canonical event lineage heads detected'
        );
    }

    return $rows[0] ?? null;
}
