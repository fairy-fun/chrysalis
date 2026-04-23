<?php

declare(strict_types=1);

require_once __DIR__ . '/assert_entity_exists.php';
require_once __DIR__ . '/assert_valid_entity_type_id.php';

/**
 * Guard canonical label writes against identity drift.
 *
 * Rules:
 * - entity must exist
 * - entity must match expected type
 * - same entity + same label => allowed
 * - same entity + different label => forbidden
 * - different entity + same type + same label => forbidden
 *
 * @throws InvalidArgumentException
 * @throws RuntimeException
 */
function assert_entity_can_accept_canonical_label(
    PDO    $pdo,
    string $entityId,
    string $entityTypeId,
    string $canonicalLabel
): void
{
    $entityId = trim($entityId);
    $entityTypeId = trim($entityTypeId);
    $canonicalLabel = trim($canonicalLabel);

    if ($canonicalLabel === '') {
        throw new InvalidArgumentException('Canonical label is required');
    }

    assert_valid_entity_type_id($pdo, $entityTypeId);

    $entity = assert_entity_exists($pdo, $entityId);

    if ($entity['entity_type_id'] !== $entityTypeId) {
        throw new RuntimeException(
            'Entity type mismatch for canonical label write: expected '
            . $entityTypeId
            . ', got '
            . $entity['entity_type_id']
        );
    }

    $stmt = $pdo->prepare(
        'SELECT canonical_label
           FROM entity_texts
          WHERE entity_id = :entity_id'
    );

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Entity already has multiple canonical label rows: ' . $entityId
        );
    }

    if (count($rows) === 1) {
        $existingLabel = (string)$rows[0]['canonical_label'];

        if ($existingLabel !== $canonicalLabel) {
            throw new RuntimeException(
                'Entity already has a different canonical label: ' . $entityId
            );
        }

        return;
    }

    $stmt = $pdo->prepare(
        'SELECT e.id
           FROM entities e
           JOIN entity_texts et
             ON et.entity_id = e.id
          WHERE e.entity_type_id = :entity_type_id
            AND et.canonical_label = :canonical_label
          LIMIT 1'
    );

    $stmt->execute([
        ':entity_type_id' => $entityTypeId,
        ':canonical_label' => $canonicalLabel,
    ]);

    $existingOwnerId = $stmt->fetchColumn();

    if ($existingOwnerId !== false && (string)$existingOwnerId !== $entityId) {
        throw new RuntimeException(
            'Canonical label is already assigned to another entity of the same type'
        );
    }
}