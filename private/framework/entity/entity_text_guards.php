<?php


declare(strict_types=1);

require_once __DIR__ . '/entity_lookup.php';

function assert_entity_can_accept_canonical_label(
    PDO    $pdo,
    string $entityId,
    string $entityTypeId,
    string $rawLabel
): void
{
    $entityId = trim($entityId);
    $entityTypeId = trim($entityTypeId);
    $label = trim($rawLabel);

    if ($entityId === '') {
        throw new InvalidArgumentException('Entity id is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    if ($label === '') {
        throw new InvalidArgumentException('Canonical label is required');
    }

    $stmt = $pdo->prepare(
        'SELECT entity_id, entity_type_id, canonical_label
         FROM sxnzlfun_chrysalis.entity_texts
         WHERE entity_id = :entity_id
         LIMIT 1'
    );
    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row !== false) {
        $existingTypeId = trim((string)($row['entity_type_id'] ?? ''));
        $existingLabel = trim((string)($row['canonical_label'] ?? ''));

        if ($existingTypeId !== $entityTypeId) {
            throw new RuntimeException(
                'Entity already has an entity_texts row with a different entity_type_id'
            );
        }

        if ($existingLabel !== $label) {
            throw new RuntimeException(
                'Entity already has a different canonical_label'
            );
        }

        return;
    }

    $existingEntityId = find_exact_entity_by_canonical_label($pdo, $label, $entityTypeId);

    if ($existingEntityId !== null && $existingEntityId !== $entityId) {
        throw new RuntimeException(
            'Canonical label is already assigned to a different entity for this entity type'
        );
    }
}