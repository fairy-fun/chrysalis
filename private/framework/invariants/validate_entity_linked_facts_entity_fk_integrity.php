<?php

declare(strict_types=1);

/**
 * Ensure every subject and object entity reference resolves.
 *
 * @return array{ok:bool, errors:list<string>}
 */
function validate_entity_linked_facts_entity_fk_integrity(PDO $pdo): array
{
    $errors = [];

    $subjectSql =
        'SELECT elf.subject_entity_id, elf.fact_type_id, elf.object_entity_id
           FROM entity_linked_facts elf
           LEFT JOIN entities e
             ON e.id = elf.subject_entity_id
          WHERE e.id IS NULL';

    $subjectRows = $pdo->query($subjectSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subjectRows as $row) {
        $errors[] =
            'Orphaned subject_entity_id='
            . (string)$row['subject_entity_id']
            . ' in entity_linked_facts for fact_type_id='
            . (string)$row['fact_type_id']
            . ', object_entity_id='
            . (string)$row['object_entity_id'];
    }

    $objectSql =
        'SELECT elf.subject_entity_id, elf.fact_type_id, elf.object_entity_id
           FROM entity_linked_facts elf
           LEFT JOIN entities e
             ON e.id = elf.object_entity_id
          WHERE e.id IS NULL';

    $objectRows = $pdo->query($objectSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($objectRows as $row) {
        $errors[] =
            'Orphaned object_entity_id='
            . (string)$row['object_entity_id']
            . ' in entity_linked_facts for subject_entity_id='
            . (string)$row['subject_entity_id']
            . ', fact_type_id='
            . (string)$row['fact_type_id'];
    }

    return [
        'ok' => count($errors) === 0,
        'errors' => $errors,
    ];
}