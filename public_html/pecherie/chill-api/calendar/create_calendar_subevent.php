<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_layer_ensurers.php';
require_once __DIR__ . '/../../../../private/framework/classvals/classval_validation.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$parentEventEntityId = $body['parent_event_entity_id'] ?? null;
$eventLabel = $body['event_label'] ?? null;

$eventTypeId = $body['event_type_id'] ?? null;
$locationId = $body['location_id'] ?? null;
$domainId = $body['domain_id'] ?? null;
$classTypeId = $body['class_type_id'] ?? null;
$beatTypeId = $body['beat_type_id'] ?? null;

$notes = $body['notes'] ?? null;
$sourceDocument = $body['source_document'] ?? null;

/** ✅ NEW: client_id intake */
$clientId = $body['client_id'] ?? null;

/**
 * Type validation
 */
foreach ([
             'event_label' => $eventLabel,
             'event_type_id' => $eventTypeId,
             'location_id' => $locationId,
             'domain_id' => $domainId,
             'class_type_id' => $classTypeId,
             'beat_type_id' => $beatTypeId,
             'notes' => $notes,
             'source_document' => $sourceDocument,
             'client_id' => $clientId,
         ] as $field => $value) {
    if ($value !== null && !is_string($value)) {
        respond(400, [
            'status' => 'error',
            'error' => "$field must be a string when provided",
        ]);
    }
}

/**
 * Normalize
 */
$parentEventEntityId = is_string($parentEventEntityId) ? trim($parentEventEntityId) : $parentEventEntityId;
$eventLabel = is_string($eventLabel) ? trim($eventLabel) : null;
$eventTypeId = is_string($eventTypeId) ? trim($eventTypeId) : null;
$locationId = is_string($locationId) ? trim($locationId) : null;
$domainId = is_string($domainId) ? trim($domainId) : null;
$classTypeId = is_string($classTypeId) ? trim($classTypeId) : null;
$beatTypeId = is_string($beatTypeId) ? trim($beatTypeId) : null;
$notes = is_string($notes) ? trim($notes) : null;
$sourceDocument = is_string($sourceDocument) ? trim($sourceDocument) : null;
$clientId = is_string($clientId) ? trim($clientId) : null;

if (!is_string($parentEventEntityId) || $parentEventEntityId === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_event_entity_id required',
    ]);
}

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

/**
 * ✅ Early idempotency check (fast path)
 */
if ($clientId !== null && $clientId !== '') {

    $stmt = $pdo->prepare("
        SELECT entity_id
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE client_id = :client_id
        LIMIT 1
    ");

    $stmt->execute([
        ':client_id' => $clientId,
    ]);

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        respond(200, [
            'status' => 'ok',
            'idempotent' => true,
            'event' => [
                'entity_id' => $existing['entity_id'],
            ],
        ]);
    }
}

try {

    /**
     * Classval validation
     */
    if ($eventTypeId !== null && $eventTypeId !== '') {
        assert_valid_classval($pdo, 'calendar_event_type_classvals', $eventTypeId, 'event_type_id');
    }

    if ($domainId !== null && $domainId !== '') {
        assert_valid_classval($pdo, 'calendar_domain_classvals', $domainId, 'domain_id');
    }

    if ($classTypeId !== null && $classTypeId !== '') {
        assert_valid_classval($pdo, 'calendar_class_type_classvals', $classTypeId, 'class_type_id');
    }

    if ($beatTypeId !== null && $beatTypeId !== '') {
        assert_valid_classval(
            $pdo,
            'cvt_calendar_beat_type',
            $beatTypeId,
            'beat_type_id'
        );
    }

    /**
     * Load parent event
     */
    $stmt = $pdo->prepare("
        SELECT
            event_type_id,
            domain_id,
            class_type_id,
            location_id
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE entity_id = :entity_id
          AND layer_id = 'calendar_layer_event'
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $parentEventEntityId,
    ]);

    $parent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($parent)) {
        respond(400, [
            'status' => 'error',
            'error' => 'parent_event_entity_id must reference a calendar event',
            'database' => $expectedDatabase,
        ]);
    }

    /**
     * Build payload with inheritance
     */
    $payload = [
        'summary' => $eventLabel !== null && $eventLabel !== ''
            ? $eventLabel
            : 'Subevent',

        'event_type_id' => $eventTypeId !== null && $eventTypeId !== ''
            ? $eventTypeId
            : ($parent['event_type_id'] ?? null),

        'domain_id' => $domainId !== null && $domainId !== ''
            ? $domainId
            : ($parent['domain_id'] ?? null),

        'class_type_id' => $classTypeId !== null && $classTypeId !== ''
            ? $classTypeId
            : ($parent['class_type_id'] ?? null),

        'location_id' => $locationId !== null && $locationId !== ''
            ? $locationId
            : ($parent['location_id'] ?? null),

        'notes' => $notes !== null && $notes !== ''
            ? $notes
            : null,

        'source_document' => $sourceDocument !== null && $sourceDocument !== ''
            ? $sourceDocument
            : null,
    ];

    $payload = array_filter(
        $payload,
        static fn($value) => $value !== null
    );

    /**
     * ✅ Attach client_id only if present
     */
    if ($clientId !== null && $clientId !== '') {
        $payload['client_id'] = $clientId;
    }

    /**
     * Beat-domain enforcement
     */
    $effectiveDomainId = $payload['domain_id'] ?? null;

    if ($beatTypeId !== null && $beatTypeId !== '') {

        $stmt = $pdo->prepare("
            SELECT 1
            FROM calendar_beat_domain_map
            WHERE beat_type_id = :beat_type_id
              AND domain_id = :domain_id
            LIMIT 1
        ");

        $stmt->execute([
            ':beat_type_id' => $beatTypeId,
            ':domain_id' => $effectiveDomainId,
        ]);

        if (!$stmt->fetch()) {
            respond(400, [
                'status' => 'error',
                'error' => 'beat_type_id not allowed in this domain',
                'beat_type_id' => $beatTypeId,
                'domain_id' => $effectiveDomainId,
            ]);
        }

        $payload['beat_type_id'] = $beatTypeId;
    }

    /**
     * Create subevent (with race-safe idempotency)
     */
    try {
        $result = ensure_calendar_subevent(
            $pdo,
            $parentEventEntityId,
            null,
            $payload
        );
    } catch (PDOException $e) {

        $info = $e->errorInfo ?? null;

        $isClientDup =
            is_array($info) &&
            (int)($info[1] ?? 0) === 1062 &&
            str_contains((string)($info[2] ?? ''), 'uq_calendar_events_client_id');

        if ($isClientDup && $clientId !== null && $clientId !== '') {

            $stmt = $pdo->prepare("
                SELECT entity_id
                FROM sxnzlfun_chrysalis.calendar_events
                WHERE client_id = :client_id
                LIMIT 1
            ");

            $stmt->execute([
                ':client_id' => $clientId,
            ]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                respond(200, [
                    'status' => 'ok',
                    'idempotent' => true,
                    'event' => [
                        'entity_id' => $existing['entity_id'],
                    ],
                ]);
            }
        }

        throw $e;
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'event' => $result,
    ]);

} catch (InvalidArgumentException $e) {

    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (RuntimeException $e) {

    respond(409, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'error' => 'Failed to create calendar subevent',
        'database' => $expectedDatabase,
    ], $e);
}