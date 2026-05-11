<?php

declare(strict_types=1);

/**
 * Read-side canonical fact resolver.
 *
 * Canonical head means:
 *   a fact row that is not superseded by any newer fact
 *   on the same ontology surface.
 *
 * This does not mean "approved canon" unless explicitly requested.
 */

function resolve_canonical_global_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $factTypeId,
    ?string $objectEntityId = null,
    bool $approvedOnly = false
): ?array {
    if ($subjectEntityId === '' || $factTypeId === '') {
        throw new InvalidArgumentException(
            'subjectEntityId and factTypeId are required'
        );
    }

    $approvedClause = $approvedOnly
        ? "AND f.adjudication_status_classval_id = 'adjudication_status_approved'"
        : '';

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
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
  {$approvedClause}
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
    bool $approvedOnly = false
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

    $approvedClause = $approvedOnly
        ? "AND f.adjudication_status_classval_id = 'adjudication_status_approved'"
        : '';

    $objectClause = $objectEntityId !== null
        ? 'AND f.object_entity_id = :object'
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
  {$approvedClause}
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