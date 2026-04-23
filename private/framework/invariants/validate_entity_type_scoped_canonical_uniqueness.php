<?php

declare(strict_types=1);

/**
 * Ensure no duplicate (entity_type_id, canonical_label) pairs exist.
 *
 * @return array{ok:bool, errors:list<string>}
 */
function validate_entity_type_scoped_canonical_uniqueness(PDO $pdo): array
{
    $sql =
        'SELECT e.entity_type_id, et.canonical_label, COUNT(*) AS duplicate_count
           FROM entities e
           JOIN entity_texts et
             ON et.entity_id = e.id
          GROUP BY e.entity_type_id, et.canonical_label
         HAVING COUNT(*) > 1';

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $errors = [];

    foreach ($rows as $row) {
        $errors[] =
            'Duplicate canonical label for entity_type_id='
            . (string)$row['entity_type_id']
            . ', canonical_label='
            . (string)$row['canonical_label']
            . ', count='
            . (string)$row['duplicate_count'];
    }

    return [
        'ok' => count($errors) === 0,
        'errors' => $errors,
    ];
}