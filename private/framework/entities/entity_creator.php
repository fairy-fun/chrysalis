<?php

declare(strict_types=1);

function create_entity(PDO $pdo, string $entityId, string $entityTypeId): void
{
    $entityId = trim($entityId);
    $entityTypeId = trim($entityTypeId);

    if ($entityId === '') {
        throw new InvalidArgumentException('entityId must be a non-empty string');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('entityTypeId must be a non-empty string');
    }

    $insert = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.entities (
            id,
            entity_type_id
        ) VALUES (
            :id,
            :entity_type_id
        )
        ON DUPLICATE KEY UPDATE
            entity_type_id = entity_type_id
    ");

    $insert->execute([
        ':id' => $entityId,
        ':entity_type_id' => $entityTypeId,
    ]);

    $check = $pdo->prepare("
        SELECT entity_type_id
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :id
        LIMIT 1
    ");

    $check->execute([':id' => $entityId]);
    $existingType = $check->fetchColumn();

    if ($existingType !== $entityTypeId) {
        throw new RuntimeException(
            "Entity type mismatch for $entityId: expected $entityTypeId, found $existingType"
        );
    }
}