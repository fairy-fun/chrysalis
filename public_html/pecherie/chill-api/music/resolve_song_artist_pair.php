<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/music/resolve_song_artist_pair.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$rawSongEntityId = $body['song_entity_id'] ?? null;

$pdo = makePdo();
$database = verifyExpectedDatabase($pdo);

try {
    $songEntityId = resolve_song_artist_pair_song_id($pdo, $rawSongEntityId);
    $result = resolve_song_artist_pair($pdo, $songEntityId);

    respond(200, [
        'status' => 'ok',
        'database' => $database,
        'data' => $result,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $database,
    ]);

} catch (RuntimeException $e) {
    respond(404, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $database,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to resolve song artist pair',
        'database' => $database,
    ], $e);
}