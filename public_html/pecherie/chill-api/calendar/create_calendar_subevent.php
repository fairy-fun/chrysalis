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
$notes = $body['notes'] ?? null;
$sourceDocument = $body['source_document'] ?? null;

foreach ([
             'event_label' => $eventLabel,
             'event_type_id' => $eventTypeId,
             'location_id' => $locationId,
             'domain_id' => $domainId,
             'class_type_id' => $classTypeId,
             'notes' => $notes,
             'source_document' => $sourceDocument,
         ] as $field => $value) {
    if ($value !== null && !is_string($value)) {
        respond(400, [
            'status' => 'error',
            'error' => "{$field} must be a string when provided",
        ]);
    }
}

$parentEventEntityId = is_string($parentEventEntityId) ? trim($parentEventEntityId) : $parentEventEntityId;
$eventLabel = is_string($eventLabel) ? trim($eventLabel) : null;
$eventTypeId = is_string($eventTypeId) ? trim($eventTypeId) : null;
$locationId = is_string($locationId) ? trim($locationId) : null;
$domainId = is_string($domainId) ? trim($domainId) : null;
$classTypeId = is_string($classTypeId) ? trim($classTypeId) : null;
$notes = is_string($notes) ? trim($notes) : null;
$sourceDocument = is_string($sourceDocument) ? trim($sourceDocument) : null;

if (!is_string($parentEventEntityId) || $parentEventEntityId === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_event_entity_id required',
    ]);
}

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    if ($eventTypeId !== null && $eventTypeId !== '') {
        assert_valid_classval(
            $pdo,
            'calendar_event_type_classvals',
            $eventTypeId,
            'event_type_id'
        );
    }

    if ($domainId !== null && $domainId !== '') {
        assert_valid_classval(
            $pdo,
            'calendar_domain_classvals',
            $domainId,
            'domain_id'
        );
    }

    if ($classTypeId !== null && $classTypeId !== '') {
        assert_valid_classval(
            $pdo,
            'calendar_class_type_classvals',
            $classTypeId,
            'class_type_id'
        );
    }

    $payload = [
        'summary' => $eventLabel !== null && $eventLabel !== ''
            ? $eventLabel
            : 'Subevent',
        'event_type_id' => $eventTypeId !== '' ? $eventTypeId : null,
        'location_id' => $locationId !== '' ? $locationId : null,
        'domain_id' => $domainId !== '' ? $domainId : null,
        'class_type_id' => $classTypeId !== '' ? $classTypeId : null,
        'notes' => $notes !== '' ? $notes : null,
        'source_document' => $sourceDocument !== '' ? $sourceDocument : null,
    ];

    $payload = array_filter(
        $payload,
        static fn($value) => $value !== null
    );

    $event = ensure_calendar_event(
        $pdo,
        $parentEventEntityId,
        null,
        $payload
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'event' => $event,
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