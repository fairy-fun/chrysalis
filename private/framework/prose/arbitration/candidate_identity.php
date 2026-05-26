<?php

declare(strict_types=1);

function prose_character_build_candidate_identity_key(array $candidate): string
{
    return implode('|', [
        trim((string)($candidate['resolved_entity_id'] ?? '')),
        trim((string)($candidate['resolution_method_classval_id'] ?? '')),
        trim((string)($candidate['matched_lookup_surface'] ?? '')),
        trim((string)($candidate['normalized_lookup_surface'] ?? '')),
        json_encode(
            array_values(
                is_array($candidate['transform_chain'] ?? null)
                    ? $candidate['transform_chain']
                    : []
            ),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ),
        trim((string)($candidate['resolver_stage'] ?? '')),
        trim((string)($candidate['arbitration_stage'] ?? '')),
    ]);
}
