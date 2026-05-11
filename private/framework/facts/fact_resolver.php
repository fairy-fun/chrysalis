<?php

declare(strict_types=1);

/**
 * Read-side canonical fact resolver.
 *
 * Canonical semantics are delegated to the database projection layer:
 *
 *   canonical_entity_linked_facts_global
 *   canonical_entity_linked_facts_event
 *
 * These views define:
 *   - supersession semantics
 *   - accepted governance semantics
 *   - canonical head selection
 *
 * The resolver layer is intentionally thin and only handles:
 *   - lookup ergonomics
 *   - optional filtering
 */

function resolve_canonical_global_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $factTypeId,
    ?string $objectEntityId = null,
    bool $acceptedOnly = false
): ?array {
    if ($subjectEntityId === '' || $factTypeId === '') {
        throw new InvalidArgumentException(
            'subjectEntityId and factTypeId are required'
        );
    }

    $acceptedClause = $acceptedOnly
        ? "AND f.adjudication_status_classval_id = 'adjudication_status_accepted'"
        : '';

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
        : '';

    $sql = <<<SQL
SELECT f.*
FROM canonical_entity_linked_facts_global f
WHERE f.subject_entity_id = :subject
  AND f.fact_type_id = :fact_type
  {$objectClause}
  {$acceptedClause}
ORDER BY f.linked_fact_id DESC
LIMIT 1
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

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function resolve_canonical_event_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $contextEntityId,
    string $factTypeId,
    ?string $objectEntityId = null,
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

    $acceptedClause = $acceptedOnly
        ? "AND f.adjudication_status_classval_id = 'adjudication_status_accepted'"
        : '';

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
        : '';

    $sql = <<<SQL
SELECT f.*
FROM canonical_entity_linked_facts_event f
WHERE f.subject_entity_id = :subject
  AND f.context_entity_id = :context
  AND f.fact_type_id = :fact_type
  {$objectClause}
  {$acceptedClause}
ORDER BY f.linked_fact_id DESC
LIMIT 1
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

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}