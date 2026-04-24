<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/expression/expression_output_resolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$rawCharacterId = $body['character_id'] ?? null;
$rawDomainId = $body['domain_id'] ?? null;

if (!is_string($rawCharacterId) || trim($rawCharacterId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'character_id must be a non-empty string',
    ]);
}

$characterId = trim($rawCharacterId);

if (is_int($rawDomainId)) {
    $domainId = $rawDomainId;
} elseif (is_string($rawDomainId) && trim($rawDomainId) !== '' && ctype_digit(trim($rawDomainId))) {
    $domainId = (int)trim($rawDomainId);
} else {
    respond(400, [
        'status' => 'error',
        'error' => 'domain_id must be a positive integer',
    ]);
}

if ($domainId < 1) {
    respond(400, [
        'status' => 'error',
        'error' => 'domain_id must be a positive integer',
    ]);
}

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $output = resolve_character_expression_output($pdo, $characterId, $domainId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'character_id' => $characterId,
        'domain_id' => $domainId,
        'output' => $output,
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
        'error' => 'Failed to resolve character expression output',
        'database' => $expectedDatabase,
    ], $e);
}
