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

    $parentEventEntityId = isset($body['parent_event_entity_id'])
        ? trim((string)$body['parent_event_entity_id'])
        : '';

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
    | Canonical parent event resolution
    |--------------------------------------------------------------------------
    |
    | Projection membership is NOT canonical parent identity.
    |
    | A projection may contain:
    |
    | - multiple root events
    | - multiple timelines
    | - unrelated event trees
    |
    | Therefore:
    |
    |   projection_id
    |
    | must never be used as implicit parent authority.
    |
    | Canonical parent identity must be explicit.
    |
    */

    if ($parentEventEntityId === '') {

        throw new RuntimeException(
            'parent_event_entity_id is required'
        );
    }

    $parentStmt = $pdo->prepare("
        SELECT
            id,
            event_id,
            projection_id,
            entity_id,
            layer_id,
            event_type_id,
            domain_id,
            class_type_id,
            location_id
        FROM calendar_events
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
    ");

    $parentStmt->execute([
        ':entity_id' => $parentEventEntityId,
    ]);

    $parentEvent = $parentStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($parentEvent)) {

        throw new RuntimeException(
            'Canonical parent event not found'
        );
    }

    $parentEventId = (int)$parentEvent['id'];

    if ($parentEventId < 1) {

        throw new RuntimeException(
            'Invalid canonical parent event'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Subevent payload
    |--------------------------------------------------------------------------
    */

    $payload = [

        'summary' => $eventLabel ?: 'Subevent',

        'subevent_index' => $subeventIndex,

        'prose_body' => $proseBody ?: null,

        'event_type_id'
        => $eventTypeId ?: $parentEvent['event_type_id'],

        'domain_id'
        => $domainId ?: $parentEvent['domain_id'],

        'class_type_id'
        => $classTypeId ?: $parentEvent['class_type_id'],

        'location_id'
        => $locationId ?: $parentEvent['location_id'],

        'beat_hash'
        => $beatHash ?: null,

        'notes'
        => $notes ?: null,

        'source_document'
        => $sourceDocument ?: null,
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
            (string)$parentEvent['entity_id'],
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
