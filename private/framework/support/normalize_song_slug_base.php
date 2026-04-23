<?php

declare(strict_types=1);

function normalize_song_slug_base(string $input): string
{
    $value = trim($input);

    // lowercase (UTF-8 safe)
    $value = mb_strtolower($value, 'UTF-8');

    // replace non-alphanumeric with underscore
    $value = preg_replace('/[^a-z0-9]+/u', '_', $value);

    // collapse multiple underscores
    $value = preg_replace('/_+/', '_', $value);

    // trim underscores from ends
    $value = trim($value, '_');

    return $value ?? '';
}