<?php

declare(strict_types=1);

/**
 * Tier 4 Surface Envelope Normaliser
 *
 * This ensures ALL prose resolvers return a consistent contract shape.
 */

function wrap_surface_response(
    string $surfaceType,
    string $status,
    string $address,
    array $entries = [],
    array $meta = []
): array {

    return [
        'surface_type' => $surfaceType,
        'status' => $status,
        'address' => $address,
        'entries' => $entries,
        'meta' => (object) $meta,
    ];
}

/**
 * Normalise chronology resolver output
 */
function wrap_chronology_surface(
    string $status,
    string $address,
    array $entries,
    array $meta = []
): array {

    return wrap_surface_response(
        'chronology',
        $status,
        $address,
        $entries,
        $meta
    );
}

/**
 * Normalise graph resolver output
 */
function wrap_graph_surface(
    string $status,
    string $address,
    array $entries,
    array $meta = []
): array {

    return wrap_surface_response(
        'graph',
        $status,
        $address,
        $entries,
        $meta
    );
}

/**
 * Mixed surface (future-proofing: cross-projection merges)
 */
function wrap_mixed_surface(
    string $status,
    string $address,
    array $entries,
    array $meta = []
): array {

    return wrap_surface_response(
        'mixed',
        $status,
        $address,
        $entries,
        $meta
    );
}