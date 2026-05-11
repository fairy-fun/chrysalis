<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/facts/fact_resolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $subjectEntityId = trim((string)($body['subject_entity_id'] ?? ''));
    $factTypeId = trim((string)($body['fact_type_id'] ?? ''));

    $objectEntityId = null;

    if (
        array_key_exists('object_entity_id', $body)
        && $body['object_entity_id'] !== null
        && trim((string)$body['object_entity_id']) !== ''
    ) {
        $objectEntityId = trim((string)$body['object_entity_id']);
    }

    $approvedOnly = false;

    if (array_key_exists('approved_only', $body)) {
        $approvedOnly = filter_var(
            $body['approved_only'],
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($approvedOnly === null) {
            respond(400, [
                'status' => 'error',
                'error' => 'approved_only must be boolean',
                'database' => $expectedDatabase,
            ]);
        }
    }

    if ($subjectEntityId === '') {
        respond(400, [
            'status' => 'error',
            'error' => 'subject_entity_id is required',
            'database' => $expectedDatabase,
        ]);
    }

    if ($factTypeId === '') {
        respond(400, [
            'status' => 'error',
            'error' => 'fact_type_id is required',
            'database' => $expectedDatabase,
        ]);
    }

    $fact = resolve_canonical_global_fact(
        $pdo,
        $subjectEntityId,
        $factTypeId,
        $objectEntityId,
        $approvedOnly
    );

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'query' => [
            'subject_entity_id' => $subjectEntityId,
            'fact_type_id' => $factTypeId,
            'object_entity_id' => $objectEntityId,
            'approved_only' => $approvedOnly,
        ],
        'found' => $fact !== null,
        'fact' => $fact,
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to resolve canonical global fact',
        'database' => $expectedDatabase,
    ], $e);
}