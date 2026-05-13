<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

function audit_fact_temporal_lineage_integrity(): array
{
    $violations = [];

    $globalViolations = audit_global_fact_temporal_lineage_integrity();
    if (!empty($globalViolations)) {
        $violations['global'] = $globalViolations;
    }

    $eventViolations = audit_event_fact_temporal_lineage_integrity();
    if (!empty($eventViolations)) {
        $violations['event'] = $eventViolations;
    }

    return $violations;
}

function audit_global_fact_temporal_lineage_integrity(): array
{
    global $pdo;

    $sql = "
        SELECT
            child.linked_fact_id   AS child_id,
            child.created_at       AS child_created_at,
            parent.linked_fact_id  AS parent_id,
            parent.created_at      AS parent_created_at
        FROM entity_linked_facts_global child
        JOIN entity_linked_facts_global parent
          ON parent.linked_fact_id =
             child.supersedes_linked_fact_id
        WHERE child.created_at < parent.created_at
        ORDER BY
            parent.created_at,
            child.created_at,
            child.linked_fact_id
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function audit_event_fact_temporal_lineage_integrity(): array
{
    global $pdo;

    $sql = "
        SELECT
            child.linked_fact_id   AS child_id,
            child.created_at       AS child_created_at,
            parent.linked_fact_id  AS parent_id,
            parent.created_at      AS parent_created_at
        FROM entity_linked_facts_event child
        JOIN entity_linked_facts_event parent
          ON parent.linked_fact_id =
             child.supersedes_linked_fact_id
        WHERE child.created_at < parent.created_at
        ORDER BY
            parent.created_at,
            child.created_at,
            child.linked_fact_id
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}