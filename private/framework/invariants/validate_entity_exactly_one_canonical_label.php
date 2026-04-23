<?php

declare(strict_types=1);

/**
 * Ensure each entity has exactly one canonical label row.
 *
 * @return array{ok:bool, errors:list<string>}
 */
function validate_entity_exactly_one_canonical_label(PDO $pdo): array
{
    $sql =
        'SELECT e.id, COUNT(et.entity_id) AS label_count
           FROM entities e
           LEFT JOIN entity_texts et
             ON et.entity_id = e.id
          GROUP BY e.id
         HAVING COUNT(et.entity_id) <> 1';

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $errors = [];

    foreach ($rows as $row) {
        $errors[] =
            'Entity '
            . (string)$row['id']
            . ' has '
            . (string)$row['label_count']
            . ' canonical label rows';
    }

    return [
        'ok' => count($errors) === 0,
        'errors' => $errors,
    ];
}