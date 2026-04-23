<?php

declare(strict_types=1);

function count_duplicate_entity_text_canonical_labels(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT COUNT(*)
         FROM (
             SELECT
                 e.entity_type_id,
                 et.canonical_label
             FROM sxnzlfun_chrysalis.entities e
             INNER JOIN sxnzlfun_chrysalis.entity_texts et
                 ON et.entity_id = e.id
             WHERE et.canonical_label IS NOT NULL
               AND TRIM(et.canonical_label) <> ""
             GROUP BY
                 e.entity_type_id,
                 et.canonical_label
             HAVING COUNT(DISTINCT e.id) > 1
         ) AS duplicates'
    );

    $count = $stmt->fetchColumn();

    if ($count === false) {
        throw new RuntimeException('Unable to count duplicate entity_text canonical labels');
    }

    return (int) $count;
}

function validate_entity_text_canonical_uniqueness(PDO $pdo): void
{
    $duplicateCount = count_duplicate_entity_text_canonical_labels($pdo);

    if ($duplicateCount > 0) {
        throw new RuntimeException(
            'Entity text canonical uniqueness violated: canonical_label must map to at most one entities.id within a given entity_type_id.'
        );
    }
}
