<?php

declare(strict_types=1);

function create_entity(
    PDO $pdo,
    string $entityId,
    string $entityTypeId
): void {

    $entityId = trim($entityId);
    $entityTypeId = trim($entityTypeId);

    if ($entityId === '') {
        throw new InvalidArgumentException(
            'entityId must be a non-empty string'
        );
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException(
            'entityTypeId must be a non-empty string'
        );
    }

    /*
     * Explicit existence check.
     *
     * Duplicate entity creation must be deliberate and visible,
     * not silently normalized through ON DUPLICATE KEY UPDATE.
     */

    $check = $pdo->prepare("
        SELECT entity_type_id
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :id
        LIMIT 1
    ");

    $check->execute([
        ':id' => $entityId,
    ]);

    $existingType = $check->fetchColumn();

    /*
     * Entity already exists.
     */

    if ($existingType !== false) {

        if ($existingType !== $entityTypeId) {
            throw new RuntimeException(
                "Entity type mismatch for {$entityId}: " .
                "expected {$entityTypeId}, found {$existingType}"
            );
        }

        return;
    }

    /*
     * Entity does not exist yet.
     */

    $insert = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.entities (
            id,
            entity_type_id
        ) VALUES (
            :id,
            :entity_type_id
        )
    ");

    try {
        $insert->execute([
            ':id' => $entityId,
            ':entity_type_id' => $entityTypeId,
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }

        $check->execute([
            ':id' => $entityId,
        ]);

        $existingType = $check->fetchColumn();

        if ($existingType === $entityTypeId) {
            return;
        }

        throw new RuntimeException(
            "Entity creation conflict for {$entityId}: " .
            "expected {$entityTypeId}, found " .
            ($existingType === false ? 'nothing' : $existingType),
            0,
            $e
        );
    }
}