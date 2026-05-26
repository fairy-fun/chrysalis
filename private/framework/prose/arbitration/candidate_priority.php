<?php

declare(strict_types=1);

function prose_character_arbitration_priority(
    string $resolutionMethod
): int {

    return match ($resolutionMethod) {
        'RESOLUTION_METHOD_EXACT_CANONICAL_LABEL' => 100,
        'RESOLUTION_METHOD_EXACT_ALIAS' => 90,
        'RESOLUTION_METHOD_NORMALIZED_ALIAS' => 80,
        'RESOLUTION_METHOD_NORMALIZED_SURNAME_ALIAS' => 70,
        'RESOLUTION_METHOD_HONORIFIC_SURNAME' => 60,
        'RESOLUTION_METHOD_TOKEN_DECOMPOSITION' => 50,
        default => 0,
    };
}
