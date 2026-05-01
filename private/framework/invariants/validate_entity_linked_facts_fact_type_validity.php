<?php

declare(strict_types=1);

require_once __DIR__ . '/../classval/classval_domains.php';

/**
 * Ensure every linked fact references a valid fact type.
 *
 * @return array{ok:bool, errors:list<string>}
 */
function validate_entity_linked_facts_fact_type_validity(PDO $pdo): array
{
    $sql = "
        SELECT fact_scope, fact_type_id
        FROM (
            SELECT
                'global' AS fact_scope,
                elf.fact_type_id
            FROM entity_linked_facts_global AS elf

            UNION ALL

            SELECT
                'event' AS fact_scope,
                elf.fact_type_id
            FROM entity_linked_facts_event AS elf
        ) AS facts
        LEFT JOIN classvals AS cv
            ON cv.id = facts.fact_type_id
           AND cv.classval_type_id = :classval_type_id
        WHERE cv.id IS NULL
        GROUP BY fact_scope, fact_type_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':classval_type_id' => ClassvalDomains::ENTITY_LINKED_FACT_TYPE,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $errors = [];

    foreach ($rows as $row) {
        $errors[] =
            'Unknown fact_type_id in ' .
            (string)$row['fact_scope'] .
            ' facts: ' .
            (string)$row['fact_type_id'];
    }

    return [
        'ok' => count($errors) === 0,
        'errors' => $errors,
    ];
}