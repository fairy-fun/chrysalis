<?php

declare(strict_types=1);

require_once __DIR__ . '/fact_governance.php';

function assert_valid_fact_as_of(string $asOf): void
{
    if (trim($asOf) === '') {
        throw new InvalidArgumentException('asOf timestamp is required');
    }
}

function resolve_global_fact_at_time(
    PDO $pdo,
    string $subjectEntityId,
    string $factTypeId,
    ?string $objectEntityId,
    string $asOf,
    bool $acceptedOnly = false
): ?array {
    if ($subjectEntityId === '' || $factTypeId === '') {
        throw new InvalidArgumentException(
            'subjectEntityId and factTypeId are required'
        );
    }

    assert_valid_fact_as_of($asOf);

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
        : '';

    $acceptedClause = $acceptedOnly
        ? 'AND f.adjudication_status_classval_id = :accepted'
        : '';

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
      WHERE newer.supersedes_linked_fact_id = f.linked_fact_id
        AND newer.created_at <= :as_of
  )
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
        $params['accepted'] = governance_accepted_adjudication_id($pdo);
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple global facts found for historical canonical slot'
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
        $subjectEntityId === '' ||
        $contextEntityId === '' ||
        $factTypeId === ''
    ) {
        throw new InvalidArgumentException(
            'subjectEntityId, contextEntityId, and factTypeId are required'
        );
    }

    assert_valid_fact_as_of($asOf);

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
        : '';

    $acceptedClause = $acceptedOnly
        ? 'AND f.adjudication_status_classval_id = :accepted'
        : '';

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
      WHERE newer.supersedes_linked_fact_id = f.linked_fact_id
        AND newer.created_at <= :as_of
  )
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
        $params['accepted'] = governance_accepted_adjudication_id($pdo);
    }

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Multiple event facts found for historical canonical slot'
        );
    }

    return $rows[0] ?? null;
}