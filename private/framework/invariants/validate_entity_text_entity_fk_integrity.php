<?php

declare(strict_types=1);

/**
 * Ensure every entity_texts.entity_id resolves to an entity.
 *
 * @return array{ok:bool, errors:list<string>}
 */
function validate_entity_text_entity_fk_integrity(PDO $pdo): array
{
    $sql =
        'SELECT et.entity_id
           FROM entity_texts et
           LEFT JOIN entities e
             ON e.id = et.entity_id
          WHERE e.id IS NULL';

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $errors = [];

    foreach ($rows as $row) {
        $errors[] = 'Orphaned entity_texts row for entity_id=' . (string)$row['entity_id'];
    }

    return [
        'ok' => count($errors) === 0,
        'errors' => $errors,
    ];
}