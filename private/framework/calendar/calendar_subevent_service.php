<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_layer_ensurers.php';

function create_calendar_subevent_core(PDO $pdo, array $body): array
{
    /*
    |--------------------------------------------------------------------------
    | Canonical runtime identity
    |--------------------------------------------------------------------------
    */

    $parentProjectionId = isset($body['parent_projection_id'])
        ? (int)$body['parent_projection_id']
        : null;

    $parentEventEntityId = $body['parent_event_entity_id'] ?? null;

    $eventLabel = $body['event_label'] ?? null;

    $eventTypeId = $body['event_type_id'] ?? null;
    $locationId = $body['location_id'] ?? null;
    $domainId = $body['domain_id'] ?? null;
    $classTypeId = $body['class_type_id'] ?? null;
    $beatTypeId = $body['beat_type_id'] ?? null;
    $beatHash = $body['beat_hash'] ?? null;

    $notes = $body['notes'] ?? null;
    $proseBody = $body['prose_body'] ?? null;
    $sourceDocument = $body['source_document'] ?? null;
    $clientId = $body['client_id'] ?? null;

    $subeventIndex = isset($body['subevent_index'])
        ? (int)$body['subevent_index']
        : null;

    /*
    |--------------------------------------------------------------------------
    | Early idempotency
    |--------------------------------------------------------------------------
    */

    if ($clientId) {

        $stmt = $pdo->prepare("
            SELECT entity_id
            FROM sxnzlfun_chrysalis.calendar_events
            WHERE client_id = :client_id
            LIMIT 1
        ");

        $stmt->execute([
            ':client_id' => $clientId,
        ]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            return [
                'status' => 'ok',
                'idempotent' => true,
                'event' => [
                    'entity_id' => $row['entity_id'],
                ],
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical parent resolution (projection-first)
    |--------------------------------------------------------------------------
    */

    $parent = null;

    if ($parentProjectionId !== null && $parentProjectionId > 0) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                event_id,
                projection_id,
                projection_entity_id,
                entity_id,
                layer_id,

                event_type_id,
                domain_id,
                class_type_id,
                location_id,

                real_date_start_id,
                real_date_end_id,

                week_index,
                day_index,
                time_index,
                event_index,

                chronology_address

            FROM sxnzlfun_chrysalis.calendar_events

            WHERE projection_id = :projection_id
              AND layer_id = 'calendar_layer_event'

            LIMIT 1
        ");

        $stmt->execute([
            ':projection_id' => $parentProjectionId,
        ]);

        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | Compatibility fallback
    |--------------------------------------------------------------------------
    */

    if (
        !$parent &&
        is_string($parentEventEntityId) &&
        trim($parentEventEntityId) !== ''
    ) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                event_id,
                projection_id,
                projection_entity_id,
                entity_id,
                layer_id,

                event_type_id,
                domain_id,
                class_type_id,
                location_id,

                real_date_start_id,
                real_date_end_id,

                week_index,
                day_index,
                time_index,
                event_index,

                chronology_address

            FROM sxnzlfun_chrysalis.calendar_events

            WHERE entity_id = :entity_id
              AND layer_id = 'calendar_layer_event'

            LIMIT 1
        ");

        $stmt->execute([
            ':entity_id' => $parentEventEntityId,
        ]);

        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$parent) {

        throw new RuntimeException(
            'Invalid parent calendar event reference'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Chronology inheritance
    |--------------------------------------------------------------------------
    */

    $payload = [

        'summary' => $eventLabel ?: 'Subevent',

        'subevent_index' => $subeventIndex,

        'prose_body' => $proseBody ?: null,

        'event_type_id'
        => $eventTypeId ?: $parent['event_type_id'],

        'domain_id'
        => $domainId ?: $parent['domain_id'],

        'class_type_id'
        => $classTypeId ?: $parent['class_type_id'],

        'location_id'
        => $locationId ?: $parent['location_id'],

        'beat_hash'
        => $beatHash ?: null,

        'notes'
        => $notes ?: null,

        'source_document'
        => $sourceDocument ?: null,

        /*
        |--------------------------------------------------------------------------
        | Persist inherited chronology
        |--------------------------------------------------------------------------
        */

        'real_date_start_id'
        => $parent['real_date_start_id'] ?? null,

        'real_date_end_id'
        => $parent['real_date_end_id'] ?? null,

        'week_index'
        => isset($parent['week_index'])
            ? (int)$parent['week_index']
            : null,

        'day_index'
        => isset($parent['day_index'])
            ? (int)$parent['day_index']
            : null,

        'time_index'
        => isset($parent['time_index'])
            ? (int)$parent['time_index']
            : null,

        'event_index'
        => isset($parent['event_index'])
            ? (int)$parent['event_index']
            : null,

        /*
        |--------------------------------------------------------------------------
        | Derived chronology address
        |--------------------------------------------------------------------------
        */

        'chronology_address'
        => build_subevent_chronology_address(
            $parent['chronology_address'] ?? null,
            $subeventIndex
        ),
    ];

    $payload = array_filter(
        $payload,
        static fn ($v) => $v !== null
    );

    if ($clientId) {
        $payload['client_id'] = $clientId;
    }

    if ($beatTypeId) {
        $payload['beat_type_id'] = $beatTypeId;
    }

    /*
    |--------------------------------------------------------------------------
    | Ensure subevent
    |--------------------------------------------------------------------------
    */

    try {

        $result = ensure_calendar_subevent(
            $pdo,
            (string)$parent['entity_id'],
            null,
            $payload
        );

        return [
            'status' => 'ok',
            'event' => $result,
        ];

    } catch (PDOException $e) {

        $info = $e->errorInfo ?? null;

        $isClientDup =
            is_array($info) &&
            (int)($info[1] ?? 0) === 1062 &&
            str_contains(
                (string)($info[2] ?? ''),
                'uq_calendar_events_client_id'
            );

        if ($isClientDup && $clientId) {

            $stmt = $pdo->prepare("
                SELECT entity_id
                FROM sxnzlfun_chrysalis.calendar_events
                WHERE client_id = :client_id
                LIMIT 1
            ");

            $stmt->execute([
                ':client_id' => $clientId,
            ]);

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                return [
                    'status' => 'ok',
                    'idempotent' => true,
                    'event' => [
                        'entity_id' => $row['entity_id'],
                    ],
                ];
            }
        }

        throw $e;
    }
}

function build_subevent_chronology_address(
    ?string $parentAddress,
    ?int $subeventIndex
): ?string {

    if (
        $parentAddress === null ||
        trim($parentAddress) === ''
    ) {
        return null;
    }

    if (
        $subeventIndex === null ||
        $subeventIndex < 1
    ) {
        return $parentAddress;
    }

    return $parentAddress . '.' . $subeventIndex;
}