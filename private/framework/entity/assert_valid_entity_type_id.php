<?php

declare(strict_types=1);

/**
 * Assert that an entity type ID exists in the registry.
 *
 * @throws InvalidArgumentException
 * @throws RuntimeException
 */
function assert_valid_entity_type_id(PDO $pdo, string $entityTypeId): void
{
    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    if ($entityTypeId !== trim($entityTypeId)) {
        throw new InvalidArgumentException('Entity type id must not contain surrounding whitespace');
    }

    $stmt = $pdo->prepare(
        'SELECT 1
           FROM entity_type_classvals
          WHERE id = :entity_type_id
          LIMIT 1'
    );

    $stmt->execute([
        ':entity_type_id' => $entityTypeId,
    ]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('Unknown entity type id: ' . $entityTypeId);
    }
}