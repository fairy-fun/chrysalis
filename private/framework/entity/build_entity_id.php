<?php
declare(strict_types=1);

require_once __DIR__ . '/normalize_slug_base_shared.php';

/**
 * Build the base candidate entity ID.
 *
 * Format:
 *   <entity_type_id>_<shared_slug>
 *
 * @throws InvalidArgumentException
 */
function build_base_entity_id(string $rawLabel, string $entityTypeId): string
{
    $rawLabel = trim($rawLabel);
    $entityTypeId = trim($entityTypeId);

    if ($rawLabel === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    $slug = normalize_slug_base_shared($rawLabel);

    return substr($entityTypeId . '_' . $slug, 0, 64);
}

/**
 * Build the deterministic fallback candidate entity ID.
 *
 * Format:
 *   <entity_type_id>_<shared_slug>_<12-char hash>
 *
 * @throws InvalidArgumentException
 */
function build_fallback_entity_id(string $rawLabel, string $entityTypeId): string
{
    $rawLabel = trim($rawLabel);
    $entityTypeId = trim($entityTypeId);

    if ($rawLabel === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    $slug = normalize_slug_base_shared($rawLabel);
    $hashSuffix = substr(hash('sha256', $entityTypeId . '|' . $rawLabel), 0, 12);
    $prefix = $entityTypeId . '_' . $slug;

    return substr($prefix . '_' . $hashSuffix, 0, 64);
}