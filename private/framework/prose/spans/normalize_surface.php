<?php

declare(strict_types=1);

function prose_character_normalize_surface(string $surfaceForm): string
{
    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return '';
    }

    return preg_replace('/\\s+/u', ' ', mb_strtolower($surfaceForm, 'UTF-8')) ?? '';
}
