<?php

declare(strict_types=1);

/**
 * Tier 4 canonical surface envelope.
 *
 * Normalizes chronology, graph, temporal, and projection resolver outputs
 * without flattening resolver semantics.
 */

function build_tier4_envelope(
    string $mode,
    string $status,
    string $address,
    ?int $projectionId,
    array $nodes,
    array $meta = []
): array {

    return [
        'status' => $status,

        'resolution' => [
            'mode' => $mode,
            'address' => $address,
            'cardinality' => derive_tier4_cardinality($nodes),
        ],

        'source' => [
            'projection_id' => $projectionId,
        ],

        'nodes' => array_values($nodes),

        'meta' => array_merge([
            'node_count' => count($nodes),
        ], $meta),
    ];
}

function build_tier4_node(
    array $event,
    array|null $publishedProse = null,
    array $meta = []
): array {

    return array_merge([
        'node_type' => 'calendar_event',
        'event' => $event,
        'published_prose' => $publishedProse,
    ], $meta);
}

function derive_tier4_cardinality(array $nodes): string
{
    return count($nodes) === 1 ? 'scalar' : 'multi';
}