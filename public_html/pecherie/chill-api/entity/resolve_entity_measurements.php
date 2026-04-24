<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/entity/entity_measurement_lookup.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$rawEntityId = $body['entity_id'] ?? null;
$rawMeasurementTypeId = $body['measurement_type_id'] ?? null;

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $entityId = resolve_measurement_lookup_entity_id($pdo, $rawEntityId);

    $measurementTypeId = is_string($rawMeasurementTypeId) && trim($rawMeasurementTypeId) !== ''
        ? trim($rawMeasurementTypeId)
        : ENTITY_MEASUREMENT_HEIGHT_TYPE_ID;

    $rows = lookup_entity_measurements($pdo, $entityId, $measurementTypeId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'entity_id' => $entityId,
        'measurement_type_id' => $measurementTypeId,
        'row_count' => count($rows),
        'rows' => $rows,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (RuntimeException $e) {
    respond(404, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to resolve entity measurements',
        'database' => $expectedDatabase,
    ], $e);
}
