<?php

declare(strict_types=1);

require_once __DIR__ . '/prose_chronology_resolver.php';
require_once __DIR__ . '/event_graph_prose_resolver.php';
require_once __DIR__ . '/story_surface_contract.php';

/**
 * Tier 4 unified story surface resolver.
 *
 * This is NOT a new data model.
 * It is an aggregation contract over existing resolvers.
 */
function resolve_story_surface(
    PDO $pdo,
    int $projectionId,
    string $address
): array {

    $address = trim($address);

    if ($projectionId < 1 || $address === '') {
        throw new InvalidArgumentException(
            'projectionId and address are required'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mode classification (single rule system)
    |--------------------------------------------------------------------------
    */

    $isChronology = preg_match('/^\d+(\.\d+)+$/', $address);
    $isGraph = str_starts_with($address, 'calendar_event:');

    /*
    |--------------------------------------------------------------------------
    | Route to correct existing resolver
    |--------------------------------------------------------------------------
    */

    if ($isGraph) {

        return resolve_event_graph_prose(
            $pdo,
            $address
        );
    }

    if ($isChronology) {

        return resolve_prose_by_chronology_address(
            $pdo,
            $projectionId,
            $address
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fallback safety (should rarely hit)
    |--------------------------------------------------------------------------
    */

    return build_story_surface(
        'unknown',
        $address,
        $projectionId,
        [],
        [],
        'unknown_surface'
    );
}