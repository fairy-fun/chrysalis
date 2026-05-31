<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_layer_ensurers.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_book_event_ensurer.php';
require_once __DIR__ . '/../../../../private/framework/classvals/classval_validation.php';

function parent_identity_is_canonical_book_time(
    PDO $pdo,
    string $parentIdentity
): bool {
    $stmt = $pdo->prepare("
        SELECT entity_id
        FROM sxnzlfun_chrysalis.calendar_book_times
        WHERE entity_id = :entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $parentIdentity,
    ]);

    $resolved = $stmt->fetchColumn();

    return is_string($resolved) && trim($resolved) !== '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$parentTimeEntityId = $body['parent_time_entity_id'] ?? null;
$eventLabel = $body['event_label'] ?? null;

$eventTypeId = $body['event_type_id'] ?? null;
$locationId = $body['location_id'] ?? null;
$classTypeId = $body['class_type_id'] ?? null;
$notes = $body['notes'] ?? null;
$sourceDocument = $body['source_document'] ?? null;

foreach ([
             'event_label' => $eventLabel,
             'event_type_id' => $eventTypeId,
             'location_id' => $locationId,
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

$parentTimeEntityId = is_string($parentTimeEntityId) ? trim($parentTimeEntityId) : $parentTimeEntityId;
$eventLabel = is_string($eventLabel) ? trim($eventLabel) : null;
$eventTypeId = is_string($eventTypeId) ? trim($eventTypeId) : null;
$locationId = is_string($locationId) ? trim($locationId) : null;
$classTypeId = is_string($classTypeId) ? trim($classTypeId) : null;
$notes = is_string($notes) ? trim($notes) : null;
$sourceDocument = is_string($sourceDocument) ? trim($sourceDocument) : null;

if (!is_string($parentTimeEntityId) || $parentTimeEntityId === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'parent_time_entity_id required',
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
            : 'Event',
        'event_type_id' => $eventTypeId !== '' ? $eventTypeId : null,
        'location_id' => $locationId !== '' ? $locationId : null,
        'class_type_id' => $classTypeId !== '' ? $classTypeId : null,
        'notes' => $notes !== '' ? $notes : null,
        'source_document' => $sourceDocument !== '' ? $sourceDocument : null,
    ];

    $payload = array_filter(
        $payload,
        static fn($value) => $value !== null
    );

    if (parent_identity_is_canonical_book_time($pdo, $parentTimeEntityId)) {
        $event = ensure_calendar_book_event(
            $pdo,
            $parentTimeEntityId,
            null,
            $payload
        );
    } else {
        $event = ensure_calendar_event(
            $pdo,
            $parentTimeEntityId,
            null,
            $payload
        );
    }

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
        'error' => 'Failed to create calendar event',
        'database' => $expectedDatabase,
    ], $e);
}
