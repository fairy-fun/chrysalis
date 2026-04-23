<?php


declare(strict_types=1);

function count_orphaned_entity_texts(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT COUNT(*)
         FROM sxnzlfun_chrysalis.entity_texts et
         LEFT JOIN sxnzlfun_chrysalis.entities e
           ON e.id = et.entity_id
         WHERE e.id IS NULL'
    );

    $count = $stmt->fetchColumn();

    if ($count === false) {
        throw new RuntimeException('Unable to count orphaned entity_texts');
    }

    return (int)$count;
}

function count_entities_with_multiple_canonical_labels(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT COUNT(*) AS duplicate_entity_count
     FROM (
         SELECT et.entity_id
         FROM sxnzlfun_chrysalis.entity_texts et
         WHERE et.canonical_label IS NOT NULL
           AND TRIM(et.canonical_label) <> ""
         GROUP BY et.entity_id
         HAVING COUNT(*) > 1
     ) AS multi_labels'
    );

    $count = $stmt->fetchColumn();

    if ($count === false) {
        throw new RuntimeException('Unable to count entities with multiple canonical labels');
    }

    return (int)$count;
}

function validate_entity_exact_match_lookup_stability(PDO $pdo): void
{
    $orphaned = count_orphaned_entity_texts($pdo);
    $multiLabels = count_entities_with_multiple_canonical_labels($pdo);

    if ($orphaned > 0 || $multiLabels > 0) {
        throw new RuntimeException(
            'Entity exact-match lookup stability violated: ' .
            'entity_texts must reference valid entities and each entity must have exactly one canonical_label.'
        );
    }
}