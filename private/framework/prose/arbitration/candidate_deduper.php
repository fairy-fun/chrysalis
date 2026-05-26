<?php

declare(strict_types=1);

require_once __DIR__ . '/candidate_identity.php';

function prose_character_append_candidate(
    array &$candidates,
    array $candidate
): void {

    $identityKey = prose_character_build_candidate_identity_key(
        $candidate
    );

    $candidates[$identityKey] = $candidate;
}
