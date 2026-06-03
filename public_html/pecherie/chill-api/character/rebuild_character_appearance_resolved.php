<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/character/resolve_character_appearance.php';

function normalize_rebuild_character_id(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    if (!is_string($value)) {
        throw new InvalidArgumentException('character_id must be a string when provided');
    }

    $value = trim($value);

    return $value === '' ? null : $value;
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
    $result = rebuild_character_appearance_resolved(
        $pdo,
        normalize_rebuild_character_id($body['character_id'] ?? null)
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'scope' => $result['scope'],
        'character_id' => $result['character_id'],
        'rebuilt_row_count' => $result['rebuilt_row_count'],
        'resolved_character_ids' => $result['resolved_character_ids'],
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
        'error' => 'Failed to rebuild character appearance resolved rows',
        'database' => $expectedDatabase,
    ], $e);
}
