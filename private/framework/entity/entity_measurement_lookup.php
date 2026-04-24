<?php


declare(strict_types=1);

const ENTITY_MEASUREMENT_HEIGHT_TYPE_ID = 'measurement_height';

function resolve_measurement_lookup_entity_id(PDO $pdo, mixed $rawEntityId): string
{
    if (is_string($rawEntityId) && trim($rawEntityId) !== '') {
        return trim($rawEntityId);
    }

    if ($rawEntityId !== null) {
        throw new InvalidArgumentException('entity_id must be a non-empty string when provided');
    }

    $stmt = $pdo->query(<<<'SQL'
SELECT rc.entity_id
FROM sxnzlfun_chrysalis.request_context rc
WHERE rc.entity_id IS NOT NULL
ORDER BY rc.created_at DESC, rc.context_id DESC
LIMIT 1
SQL
    );

    $entityId = $stmt->fetchColumn();
    if (!is_string($entityId) || trim($entityId) === '') {
        throw new RuntimeException('No entity_id found in request_context');
    }

    return trim($entityId);
}

function lookup_entity_measurements(PDO $pdo, string $entityId, string $measurementTypeId = ENTITY_MEASUREMENT_HEIGHT_TYPE_ID): array
{
    $entityId = trim($entityId);
    if ($entityId === '') {
        throw new InvalidArgumentException('entity_id must be a non-empty string');
    }

    $measurementTypeId = trim($measurementTypeId);
    if ($measurementTypeId === '') {
        throw new InvalidArgumentException('measurement_type_id must be a non-empty string');
    }

    $stmt = $pdo->prepare(<<<'SQL'
SELECT
    cm.measurement_id,
    cm.character_entity_id,
    cm.measurement_type_id,
    cm.value_decimal,
    cm.unit,
    cm.created_at
FROM sxnzlfun_chrysalis.character_measurements cm
WHERE cm.character_entity_id = :entity_id
  AND cm.measurement_type_id = :measurement_type_id
ORDER BY cm.created_at DESC, cm.measurement_id DESC
SQL
    );

    $stmt->execute([
        'entity_id' => $entityId,
        'measurement_type_id' => $measurementTypeId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
