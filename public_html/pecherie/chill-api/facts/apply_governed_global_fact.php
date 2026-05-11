<?php

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/facts/apply_fact.php';

requireAuth();

$body = getJsonBody();

$pdo = makePdo('write');

$result = apply_global_fact(
    $pdo,
    $body['subject_entity_id'],
    $body['fact_type_id'],
    $body['object_entity_id'] ?? null,
    $body['source_document'],
    $body['notes'] ?? null,
    $body['governance'] ?? null,
    $body['supersedes_linked_fact_id'] ?? null
);

respond(200, [
    'status' => 'ok',
    'operation' => 'applyGovernedGlobalFact',
    'result' => $result,
]);