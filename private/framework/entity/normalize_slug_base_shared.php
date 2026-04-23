<?php

declare(strict_types=1);

/**
 * Shared slug-normalisation boundary for helper ID generation.
 *
 * Contract:
 * - preserves locked legacy slug behaviour exactly
 * - deterministic
 * - never used for canonical label matching
 *
 * @throws InvalidArgumentException
 */
function normalize_slug_base_shared(string $rawLabel): string
{
    $rawLabel = trim($rawLabel);

    if ($rawLabel === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    // Locked compatibility boundary.
    return normalize_song_slug_base($rawLabel);
}