<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/calendar/calendar_projection_materializer.php';

function normalize_projection_rebuild_request(array $body): array
{
    $projectionIds = $body['projection_ids'] ?? null;
    $singleProjectionId = $body['projection_id'] ?? null;

    if ($projectionIds === null && $singleProjectionId !== null) {
        $projectionIds = [$singleProjectionId];
    }

    if (!is_array($projectionIds) || $projectionIds === []) {
        throw new InvalidArgumentException(
            'projection_ids must be a non-empty array of positive integers'
        );
    }

    $normalized = [];

    foreach ($projectionIds as $index => $projectionId) {
        if (!is_int($projectionId) || $projectionId < 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'projection_ids[%d] must be a positive integer',
                    $index
                )
            );
        }

        $normalized[$projectionId] = $projectionId;
    }

    return array_values($normalized);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $projectionIds = normalize_projection_rebuild_request($body);
    $rebuiltProjectionIds = [];

    foreach ($projectionIds as $projectionId) {
        $rebuiltProjectionIds[] = rebuild_calendar_projection(
            $pdo,
            $projectionId
        );
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'rebuilt_projection_ids' => $rebuiltProjectionIds,
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
        'status' => 'error',
        'error' => 'Failed to rebuild calendar projections',
        'database' => $expectedDatabase,
    ], $e);
}
