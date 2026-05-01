<?php

declare(strict_types=1);

/**
 * Ensure every subject, object, and event context entity reference resolves.
 *
 * @return array{ok:bool, errors:list<string>}
 */
function validate_entity_linked_facts_entity_fk_integrity(PDO $pdo): array
{
    $errors = [];

    $subjectSql = "
        SELECT facts.fact_scope, facts.subject_entity_id, facts.fact_type_id, facts.object_entity_id
        FROM (
            SELECT
                'global' AS fact_scope,
                elf.subject_entity_id,
                elf.fact_type_id,
                elf.object_entity_id
            FROM entity_linked_facts_global AS elf

            UNION ALL

            SELECT
                'event' AS fact_scope,
                elf.subject_entity_id,
                elf.fact_type_id,
                elf.object_entity_id
            FROM entity_linked_facts_event AS elf
        ) AS facts
        LEFT JOIN entities AS e
            ON e.id = facts.subject_entity_id
        WHERE e.id IS NULL
    ";

    $subjectRows = $pdo->query($subjectSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subjectRows as $row) {
        $errors[] =
            'Orphaned subject_entity_id='
            . (string)$row['subject_entity_id']
            . ' in '
            . (string)$row['fact_scope']
            . ' facts for fact_type_id='
            . (string)$row['fact_type_id']
            . ', object_entity_id='
            . (string)$row['object_entity_id'];
    }

    $objectSql = "
        SELECT facts.fact_scope, facts.subject_entity_id, facts.fact_type_id, facts.object_entity_id
        FROM (
            SELECT
                'global' AS fact_scope,
                elf.subject_entity_id,
                elf.fact_type_id,
                elf.object_entity_id
            FROM entity_linked_facts_global AS elf

            UNION ALL

            SELECT
                'event' AS fact_scope,
                elf.subject_entity_id,
                elf.fact_type_id,
                elf.object_entity_id
            FROM entity_linked_facts_event AS elf
        ) AS facts
        LEFT JOIN entities AS e
            ON e.id = facts.object_entity_id
        WHERE e.id IS NULL
    ";

    $objectRows = $pdo->query($objectSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($objectRows as $row) {
        $errors[] =
            'Orphaned object_entity_id='
            . (string)$row['object_entity_id']
            . ' in '
            . (string)$row['fact_scope']
            . ' facts for subject_entity_id='
            . (string)$row['subject_entity_id']
            . ', fact_type_id='
            . (string)$row['fact_type_id'];
    }

    $contextSql = "
        SELECT
            elf.subject_entity_id,
            elf.context_entity_id,
            elf.fact_type_id,
            elf.object_entity_id
        FROM entity_linked_facts_event AS elf
        LEFT JOIN entities AS e
            ON e.id = elf.context_entity_id
        WHERE e.id IS NULL
    ";

    $contextRows = $pdo->query($contextSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($contextRows as $row) {
        $errors[] =
            'Orphaned context_entity_id='
            . (string)$row['context_entity_id']
            . ' in event facts for subject_entity_id='
            . (string)$row['subject_entity_id']
            . ', fact_type_id='
            . (string)$row['fact_type_id']
            . ', object_entity_id='
            . (string)$row['object_entity_id'];
    }

    return [
        'ok' => count($errors) === 0,
        'errors' => $errors,
    ];
}
