<?php

declare(strict_types=1);

require_once __DIR__ . '/assert_valid_entity_type_id.php';

/**
 * Exact canonical lookup scoped by entity type.
 *
 * Returns null if missing.
 * Fails closed on ambiguity.
 *
 * @return array{id:string, entity_type_id:string, canonical_label:string}|null
 *
 * @throws InvalidArgumentException
 * @throws RuntimeException
 */
function find_exact_entity_by_canonical_label(
    PDO    $pdo,
    string $rawLabel,
    string $entityTypeId
): ?array
{
    $rawLabel = trim($rawLabel);
    $entityTypeId = trim($entityTypeId);

    if ($rawLabel === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    assert_valid_entity_type_id($pdo, $entityTypeId);

    $stmt = $pdo->prepare(
        'SELECT e.id, e.entity_type_id, et.canonical_label
           FROM entities e
           JOIN entity_texts et
             ON et.entity_id = e.id
          WHERE e.entity_type_id = :entity_type_id
            AND et.canonical_label = :canonical_label'
    );

    $stmt->execute([
        ':entity_type_id' => $entityTypeId,
        ':canonical_label' => $rawLabel,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($rows);

    if ($count > 1) {
        throw new RuntimeException('Ambiguous exact canonical label match for entity type');
    }

    if ($count === 0) {
        return null;
    }

    return [
        'id' => (string)$rows[0]['id'],
        'entity_type_id' => (string)$rows[0]['entity_type_id'],
        'canonical_label' => (string)$rows[0]['canonical_label'],
    ];
}