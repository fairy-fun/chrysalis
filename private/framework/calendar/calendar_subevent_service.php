<?php

require_once __DIR__ . '/calendar_layer_ensurers.php';

function create_calendar_subevent_core(PDO $pdo, array $body): array
{
$parentEventEntityId = $body['parent_event_entity_id'] ?? null;
$eventLabel = $body['event_label'] ?? null;

$eventTypeId = $body['event_type_id'] ?? null;
$locationId = $body['location_id'] ?? null;
$domainId = $body['domain_id'] ?? null;
$classTypeId = $body['class_type_id'] ?? null;
$beatTypeId = $body['beat_type_id'] ?? null;

$notes = $body['notes'] ?? null;
$sourceDocument = $body['source_document'] ?? null;
$clientId = $body['client_id'] ?? null;

// --- EARLY IDEMPOTENCY (unchanged) ---
if ($clientId) {
$stmt = $pdo->prepare("
SELECT entity_id
FROM sxnzlfun_chrysalis.calendar_events
WHERE client_id = :client_id
LIMIT 1
");
$stmt->execute([':client_id' => $clientId]);

if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
return [
'status' => 'ok',
'idempotent' => true,
'event' => ['entity_id' => $row['entity_id']],
];
}
}

// --- LOAD PARENT (critical, was missing in orchestrator) ---
$stmt = $pdo->prepare("
SELECT event_type_id, domain_id, class_type_id, location_id
FROM sxnzlfun_chrysalis.calendar_events
WHERE entity_id = :entity_id
AND layer_id = 'calendar_layer_event'
LIMIT 1
");
$stmt->execute([':entity_id' => $parentEventEntityId]);

$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
throw new RuntimeException('Invalid parent_event_entity_id');
}

// --- INHERITANCE (critical) ---
$payload = [
'summary' => $eventLabel ?: 'Subevent',
'event_type_id' => $eventTypeId ?: $parent['event_type_id'],
'domain_id' => $domainId ?: $parent['domain_id'],
'class_type_id' => $classTypeId ?: $parent['class_type_id'],
'location_id' => $locationId ?: $parent['location_id'],
'notes' => $notes ?: null,
'source_document' => $sourceDocument ?: null,
];

$payload = array_filter($payload, fn($v) => $v !== null);

if ($clientId) {
$payload['client_id'] = $clientId;
}

// --- ENSURE ---
try {
$result = ensure_calendar_subevent(
$pdo,
$parentEventEntityId,
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
str_contains((string)($info[2] ?? ''), 'uq_calendar_events_client_id');

if ($isClientDup && $clientId) {

$stmt = $pdo->prepare("
SELECT entity_id
FROM sxnzlfun_chrysalis.calendar_events
WHERE client_id = :client_id
LIMIT 1
");
$stmt->execute([':client_id' => $clientId]);

if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
return [
'status' => 'ok',
'idempotent' => true,
'event' => ['entity_id' => $row['entity_id']],
];
}
}

throw $e;
}
}