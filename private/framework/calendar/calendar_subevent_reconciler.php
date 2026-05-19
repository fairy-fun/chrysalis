<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_subevent_service.php';
require_once __DIR__ . '/../prose/prose_subevent_segmenter.php';

/**
 * Canonical chronology-authoritative subevent reconciler.
 *
 * DOCTRINE
 *
 * - chronology identity is authoritative
 * - prose is mutable attached content
 * - chronology topology must survive prose edits
 * - chronology_address is durable
 * - subevent_index is canonical parent-relative identity
 * - reconciliation must preserve existing durable identities
 *
 * IMPORTANT
 *
 * This layer does NOT:
 *
 * - regenerate chronology topology
 * - rewrite chronology_address
 * - rewrite parent_event_id
 * - rewrite sequence_index
 * - rewrite subevent_index
 * - hard delete orphaned subevents
 * - mutate lifecycle state through notes
 *
 * This layer ONLY:
 *
 * - loads canonical existing subevents
 * - derives desired semantic prose segmentation
 * - updates matching durable nodes
 * - allocates genuinely new subevents
 * - reports orphaned nodes separately
 */

/**
 * Reconcile durable subevents against prose semantics.
 */
function reconcile_calendar_subevents(
    PDO $pdo,
    array $parentEvent,
    string $prose,
    array $options = []
): array {

    $parentEntityId = trim(
        (string)($parentEvent['entity_id'] ?? '')
    );

    if ($parentEntityId === '') {

        throw new InvalidArgumentException(
            'Missing parent event entity_id'
        );
    }

    /*
     * -------------------------------------------------------------------------
     * Canonical existing topology
     * -------------------------------------------------------------------------
     */

    $existingSubevents = load_existing_calendar_subevents(
        $pdo,
        $parentEntityId
    );

    /*
     * -------------------------------------------------------------------------
     * Desired semantic segmentation
     * -------------------------------------------------------------------------
     */

    $segments = segment_prose_into_subevents(
        $pdo,
        $prose
    );

    /*
     * -------------------------------------------------------------------------
     * Canonical reconciliation
     * -------------------------------------------------------------------------
     */

    $results = [];

    $position = 0;

    foreach ($segments as $segment) {

        $position++;

        $existing = $existingSubevents[$position] ?? null;

        /*
         * ---------------------------------------------------------------------
         * Preserve durable identity
         * ---------------------------------------------------------------------
         */

        if ($existing !== null) {

            $results[] = reconcile_existing_calendar_subevent(
                $pdo,
                $existing,
                $segment
            );

            continue;
        }

        /*
         * ---------------------------------------------------------------------
         * Allocate genuinely new durable subevent
         * ---------------------------------------------------------------------
         */

        $results[] = create_reconciled_calendar_subevent(
            $pdo,
            $parentEvent,
            $segment,
            $position,
            $options
        );
    }

    /*
     * -------------------------------------------------------------------------
     * Structural orphan detection
     * -------------------------------------------------------------------------
     */

    $orphaned = [];

    if (count($existingSubevents) > count($segments)) {

        $orphaned = array_slice(
            $existingSubevents,
            count($segments)
        );
    }

    return [

        'status' => 'ok',

        'parent_event' => [

            'entity_id'
            => $parentEntityId,

            'projection_id'
            => $parentEvent['projection_id']
                ?? null,
        ],

        'summary' => [

            'existing_count'
            => count($existingSubevents),

            'desired_count'
            => count($segments),

            'reconciled_count'
            => count($results),

            'orphaned_count'
            => count($orphaned),
        ],

        'subevents'
        => array_values($results),

        'orphaned_subevents'
        => array_values($orphaned),
    ];
}

/**
 * Load canonical durable subevents.
 *
 * IMPORTANT:
 * subevents are identified by:
 *
 * layer_id = calendar_layer_subevent
 *
 * NOT merely by parenthood.
 */
function load_existing_calendar_subevents(
    PDO $pdo,
    string $parentEntityId
): array {

    $sql = "
        SELECT
            ce.id,
            ce.entity_id,
            ce.event_id,
            ce.parent_event_id,

            ce.layer_id,

            ce.summary,
            ce.prose_body,

            ce.subevent_index,
            ce.sequence_index,

            ce.chronology_address,

            ce.beat_type_id,
            ce.beat_hash,

            ce.projection_id,

            ce.created_at,
            ce.updated_at

        FROM calendar_events ce

        INNER JOIN calendar_events parent
            ON parent.event_id = ce.parent_event_id

        WHERE parent.entity_id = :parent_entity_id
          AND ce.layer_id = 'calendar_layer_subevent'

        ORDER BY
            ce.subevent_index ASC,
            ce.sequence_index ASC,
            ce.id ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':parent_entity_id' => $parentEntityId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($rows)) {
        return [];
    }

    /*
     * -------------------------------------------------------------------------
     * Canonical parent-relative index map
     * -------------------------------------------------------------------------
     */

    $indexed = [];

    foreach ($rows as $row) {

        $subeventIndex = isset($row['subevent_index'])
            ? (int)$row['subevent_index']
            : null;

        if ($subeventIndex === null || $subeventIndex < 1) {
            continue;
        }

        $indexed[$subeventIndex] = $row;
    }

    ksort($indexed);

    return $indexed;
}

/**
 * Reconcile mutable semantic content
 * while preserving chronology identity.
 */
function reconcile_existing_calendar_subevent(
    PDO $pdo,
    array $existing,
    array $segment
): array {

    $sql = "
        UPDATE calendar_events

        SET
            summary = :summary,
            prose_body = :prose_body,
            beat_type_id = :beat_type_id,
            beat_hash = :beat_hash,
            updated_at = NOW()

        WHERE id = :id

        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        ':summary'
        => $segment['summary'] ?? null,

        ':prose_body'
        => $segment['prose_body'] ?? null,

        ':beat_type_id'
        => $segment['beat_type_id'] ?? null,

        ':beat_hash'
        => $segment['beat_hash'] ?? null,

        ':id'
        => $existing['id'],
    ]);

    return [

        'status' => 'updated',

        'event' => [

            'entity_id'
            => $existing['entity_id'],

            'event_id'
            => $existing['event_id'],

            'chronology_address'
            => $existing['chronology_address'],

            'subevent_index'
            => $existing['subevent_index'],

            'sequence_index'
            => $existing['sequence_index'],
        ],
    ];
}

/**
 * Allocate genuinely new durable subevent.
 */
function create_reconciled_calendar_subevent(
    PDO $pdo,
    array $parentEvent,
    array $segment,
    int $subeventIndex,
    array $options = []
): array {

    $payload = [

        'parent_projection_id'
        => $parentEvent['projection_id']
            ?? null,

        'parent_event_entity_id'
        => $parentEvent['entity_id'],

        'event_label'
        => $segment['summary']
            ?? 'Subevent',

        'beat_type_id'
        => $segment['beat_type_id']
            ?? null,

        'beat_hash'
        => $segment['beat_hash']
            ?? null,

        'prose_body'
        => $segment['prose_body']
            ?? null,

        /*
         * Canonical durable parent-relative identity
         */
        'subevent_index'
        => $subeventIndex,

        'source_document'
        => $options['source_document']
            ?? null,

        /*
         * Optional deterministic idempotency
         */
        'client_id'
        => build_reconciled_subevent_client_id(
            $parentEvent,
            $subeventIndex,
            $options
        ),
    ];

    $result = create_calendar_subevent_core(
        $pdo,
        $payload
    );

    return [

        'status' => 'created',

        'event'
        => $result['event']
            ?? null,
    ];
}

/**
 * Deterministic reconciliation-scoped client id.
 */
function build_reconciled_subevent_client_id(
    array $parentEvent,
    int $subeventIndex,
    array $options = []
): ?string {

    $prefix = trim(
        (string)(
            $options['client_id_prefix']
            ?? ''
        )
    );

    if ($prefix === '') {
        return null;
    }

    $parentEntityId = trim(
        (string)(
            $parentEvent['entity_id']
            ?? ''
        )
    );

    if ($parentEntityId === '') {
        return null;
    }

    return implode(':', [

        $prefix,
        'subevent',
        $parentEntityId,
        (string)$subeventIndex,
    ]);
}