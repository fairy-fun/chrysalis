<?php

declare(strict_types=1);

require_once __DIR__ . '/resolve_exact_canonical.php';
require_once __DIR__ . '/resolve_exact_alias.php';
require_once __DIR__ . '/resolve_normalized_alias.php';
require_once __DIR__ . '/resolve_normalized_surname_alias.php';
require_once __DIR__ . '/resolve_honorific_surname.php';
require_once __DIR__ . '/resolve_token_decomposition.php';

function prose_character_resolution_pipeline(): array
{
    return [
        'prose_character_try_exact_canonical_label',
        'prose_character_try_exact_alias',
        'prose_character_try_normalized_alias',
        'prose_character_try_honorific_surname',
        'prose_character_try_token_decomposition',
    ];
}
