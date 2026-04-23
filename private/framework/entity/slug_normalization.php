<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/normalize_song_slug_base.php';

function normalize_slug_base_shared(string $rawLabel): string
{
    $label = trim($rawLabel);

    if ($label === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    $slug = normalize_song_slug_base($label);

    if (!is_string($slug)) {
        throw new RuntimeException('Shared slug normalization returned a non-string value');
    }

    $slug = trim($slug);

    if ($slug === '') {
        throw new RuntimeException('Shared slug normalization returned an empty slug');
    }

    return $slug;
}