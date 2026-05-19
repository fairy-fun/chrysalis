<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

require_once __DIR__
    . '/../../../../private/framework/prose/prose_chronology_resolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

try {

    $body = getJsonBody();

    $projectionId = $body['projection_id'] ?? null;
    $chronologyAddress = $body['chronology_address'] ?? null;
    $weekIndex = $body['week_index'] ?? null;
    $dayIndex = $body['day_index'] ?? null;

    if (
        !is_int($projectionId)
        && !(
            is_string($projectionId)
            && ctype_digit($projectionId)
        )
    ) {
        throw new InvalidArgumentException(
            'projection_id is required'
        );
    }

    $hasChronologyAddress =
        is_string($chronologyAddress)
        && trim($chronologyAddress) !== '';

    $hasWeekDay =
        (
            is_int($weekIndex)
            || (
                is_string($weekIndex)
                && ctype_digit($weekIndex)
            )
        )
        && (
            is_int($dayIndex)
            || (
                is_string($dayIndex)
                && ctype_digit($dayIndex)
            )
        );

    if (!$hasChronologyAddress && !$hasWeekDay) {
        throw new InvalidArgumentException(
            'Provide either chronology_address or week_index/day_index'
        );
    }

    $pdo = makePdo('read');

    $expectedDatabase = verifyExpectedDatabase($pdo);

    if ($hasChronologyAddress) {

        $cleanChronologyAddress = trim($chronologyAddress);

        $result = resolve_prose_by_chronology_address(
            $pdo,
            (int)$projectionId,
            $cleanChronologyAddress
        );

        respond(200, [
            'status' => 'ok',
            'database' => $expectedDatabase,
            'projection_id' => (int)$projectionId,
            'chronology_address' => $cleanChronologyAddress,
            'result' => $result,
        ]);
    }

    $result = resolve_prose_by_week_day(
        $pdo,
        (int)$projectionId,
        (int)$weekIndex,
        (int)$dayIndex
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'projection_id' => (int)$projectionId,
        'week_index' => (int)$weekIndex,
        'day_index' => (int)$dayIndex,
        'result' => $result,
    ]);

} catch (InvalidArgumentException $e) {

    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to resolve prose by chronology',
    ], $e);
}