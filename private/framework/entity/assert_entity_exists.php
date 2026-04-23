<?php

declare(strict_types=1);

/**
 * Assert that an entity exists and return its core record.
 *
 * Returns:
 * - id
 * - entity_type_id
 *
 * @return array{id:string, entity_type_id:string}
 *
 * @throws InvalidArgumentException
 * @throws RuntimeException
 */
function assert_entity_exists(PDO $pdo, string $entityId): array
{
    $entityId = trim($entityId);

    if ($entityId === '') {
        throw new InvalidArgumentException('Entity id is required');
    }

    $stmt = $pdo->prepare(
        'SELECT id, entity_type_id
           FROM entities
          WHERE id = :entity_id
          LIMIT 1'
    );

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        throw new RuntimeException('Unknown entity id: ' . $entityId);
    }

    return [
        'id' => (string)$row['id'],
        'entity_type_id' => (string)$row['entity_type_id'],
    ];
}