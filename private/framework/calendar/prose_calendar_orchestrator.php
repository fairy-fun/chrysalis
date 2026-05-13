<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_subevent_segmenter.php';

/**
 * Canonical prose → calendar subevent orchestration runtime.
 *
 * Doctrine:
 * - deterministic segmentation
 * - canonical slot ordering
 * - replay-safe chronology identity
 * - idempotent persistence
 * - no planner diffing
 * - no beat hash reconciliation
 * - no mutable reconciliation layer
 *
 * Runtime flow:
 *
 * prose
 * → deterministic segmentation
 * → canonical slot assignment
 * → replay-safe client ids
 * → canonical persistence
 */
function execute_calendar_batch_from_prose(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {

    $parentEventEntityId = trim($parentEventEntityId);

    if ($parentEventEntityId === '') {
        throw new InvalidArgumentException(
            'parentEventEntityId must be non-empty'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical deterministic segmentation
    |--------------------------------------------------------------------------
    */

    $subevents = segment_prose_into_subevents(
        $prose
    );

    /*
    |--------------------------------------------------------------------------
    | Empty chronology
    |--------------------------------------------------------------------------
    */

    if ($subevents === []) {

        return [
            'status' => 'ok',
            'parent_event_entity_id' => $parentEventEntityId,
            'subevent_count' => 0,
            'persisted_count' => 0,
            'results' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical persistence
    |--------------------------------------------------------------------------
    */

    $persisted = persist_segmented_subevents(
        $pdo,
        $parentEventEntityId,
        $subevents
    );

    /*
    |--------------------------------------------------------------------------
    | Runtime result surface
    |--------------------------------------------------------------------------
    */

    return [
        'status' => 'ok',

        'parent_event_entity_id' => $parentEventEntityId,

        'subevent_count' => count($subevents),

        'persisted_count' => count($persisted),

        'results' => $persisted,
    ];
}