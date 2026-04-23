<?php

declare(strict_types=1);

require_once __DIR__ . '/slug_normalization.php';

function build_entity_base_id(string $rawLabel, string $entityTypeId): string
{
    $entityTypeId = trim($entityTypeId);
    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    $baseSlug = normalize_slug_base_shared($rawLabel);

    return substr($entityTypeId . '_' . $baseSlug, 0, 64);
}

function build_entity_fallback_id(string $rawLabel, string $entityTypeId): string
{
    $label = trim($rawLabel);
    $entityTypeId = trim($entityTypeId);

    if ($label === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    $baseSlug = normalize_slug_base_shared($label);
    $hashSuffix = substr(hash('sha256', $entityTypeId . '|' . $label), 0, 12);
    $prefix = $entityTypeId . '_' . $baseSlug;

    return substr($prefix . '_' . $hashSuffix, 0, 64);
}

function entity_id_exists(PDO $pdo, string $entityId): bool
{
    $sql = <<<'SQL'
    SELECT 1
    FROM sxnzlfun_chrysalis.entities
    WHERE id = :entity_id
    LIMIT 1
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchColumn() !== false;
}

function resolve_or_suggest_entity_id(PDO $pdo, string $rawLabel, string $entityTypeId): array
{
    $label = trim($rawLabel);
    $entityTypeId = trim($entityTypeId);

    if ($label === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    $baseEntityId = build_entity_base_id($label, $entityTypeId);

    if (!entity_id_exists($pdo, $baseEntityId)) {
        return [
            'entity_id' => $baseEntityId,
            'resolution_code' => 'base_available',
        ];
    }

    $fallbackEntityId = build_entity_fallback_id($label, $entityTypeId);

    if (!entity_id_exists($pdo, $fallbackEntityId)) {
        return [
            'entity_id' => $fallbackEntityId,
            'resolution_code' => 'fallback_available',
        ];
    }

    throw new RuntimeException('Entity resolution collision (base + fallback both exist)');
}