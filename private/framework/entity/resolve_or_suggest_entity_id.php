<?php
declare(strict_types=1);

require_once __DIR__ . '/assert_valid_entity_type_id.php';
require_once __DIR__ . '/build_entity_id.php';

/**
 * Resolve the next legal entity ID suggestion without writing.
 *
 * Returns:
 * - entity_id
 * - mode: base_available | fallback_available
 *
 * @return array{entity_id:string, mode:string}
 *
 * @throws InvalidArgumentException
 * @throws RuntimeException
 */
function resolve_or_suggest_entity_id(
    PDO $pdo,
    string $rawLabel,
    string $entityTypeId
): array {
    $rawLabel = trim($rawLabel);
    $entityTypeId = trim($entityTypeId);

    if ($rawLabel === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    assert_valid_entity_type_id($pdo, $entityTypeId);

    $baseEntityId = build_base_entity_id($rawLabel, $entityTypeId);

    $stmt = $pdo->prepare(
        'SELECT 1
           FROM entities
          WHERE id = :entity_id
          LIMIT 1'
    );

    $stmt->execute([
        ':entity_id' => $baseEntityId,
    ]);

    if ($stmt->fetchColumn() === false) {
        return [
            'entity_id' => $baseEntityId,
            'mode' => 'base_available',
        ];
    }

    $fallbackEntityId = build_fallback_entity_id($rawLabel, $entityTypeId);

    $stmt->execute([
        ':entity_id' => $fallbackEntityId,
    ]);

    if ($stmt->fetchColumn() === false) {
        return [
            'entity_id' => $fallbackEntityId,
            'mode' => 'fallback_available',
        ];
    }

    throw new RuntimeException('Entity resolution collision (base + fallback both exist)');
}