<?php

declare(strict_types=1);

require_once __DIR__ . '/normalize_surface.php';

function prose_character_tokenize_surface(string $surfaceForm): array
{
    $normalized = prose_character_normalize_surface($surfaceForm);

    if ($normalized === '') {
        return [];
    }

    $tokens = preg_split('/\\s+/u', $normalized);

    return is_array($tokens)
        ? array_values(array_filter(
            $tokens,
            static fn ($token): bool => trim((string)$token) !== ''
        ))
        : [];
}
