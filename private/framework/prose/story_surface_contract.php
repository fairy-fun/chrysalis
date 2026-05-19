<?php

declare(strict_types=1);

require_once __DIR__ . '/prose_surface_envelope.php';

/**
 * Tier 4 story surface façade.
 *
 * IMPORTANT:
 *
 * This layer does NOT redefine:
 * - node construction
 * - cardinality derivation
 * - envelope semantics
 *
 * Existing Tier 4 primitives remain authoritative.
 *
 * This layer only standardizes resolver entrypoints.
 */

function build_story_surface(
    string $mode,
    string $address,
    ?int $projectionId,
    array $nodes = [],
    array $meta = [],
    string $status = 'ok'
): array {

    $normalizedNodes = [];

    foreach ($nodes as $node) {

        /*
         * Preserve existing normalized nodes only.
         *
         * IMPORTANT:
         * Require published_prose key presence to preserve
         * canonical Tier 4 node contract integrity.
         */
        if (
            is_array($node)
            && array_key_exists('node_type', $node)
            && array_key_exists('event', $node)
            && array_key_exists('published_prose', $node)
        ) {
            $normalizedNodes[] = $node;
        }
    }

    /*
     * IMPORTANT:
     * Delegate final envelope authority to the
     * canonical Tier 4 primitive.
     */
    return build_tier4_envelope(
        $mode,
        $status,
        $address,
        $projectionId,
        $normalizedNodes,
        $meta
    );
}

/**
 * Convenience helper:
 * chronology surface.
 */
function build_chronology_surface(
    string $address,
    ?int $projectionId,
    array $nodes = [],
    array $meta = [],
    string $status = 'ok'
): array {

    return build_story_surface(
        'chronology',
        $address,
        $projectionId,
        $nodes,
        $meta,
        $status
    );
}

/**
 * Convenience helper:
 * graph surface.
 */
function build_graph_surface(
    string $address,
    ?int $projectionId,
    array $nodes = [],
    array $meta = [],
    string $status = 'ok'
): array {

    return build_story_surface(
        'graph',
        $address,
        $projectionId,
        $nodes,
        $meta,
        $status
    );
}