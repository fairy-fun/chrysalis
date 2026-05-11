<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../private/db.php';
require_once __DIR__ . '/../../../../private/framework/facts/apply_fact.php';

$body = read_request_body();

try {
    $required = [
        'subject_entity_id',
        'context_entity_id',
        'fact_type_id',
        'object_entity_id',
        'source_document',
    ];

    foreach ($required as $field) {
        if (
            !array_key_exists($field, $body)
            || $body[$field] === null
            || $body[$field] === ''
        ) {
            throw new InvalidArgumentException(
                "Missing required field: {$field}"
            );
        }
    }

    $pdo = db();

    $result = apply_event_fact(
        $pdo,
        $body['subject_entity_id'],
        $body['context_entity_id'],
        $body['fact_type_id'],
        $body['object_entity_id'],
        $body['source_document'],
        $body['notes'] ?? null,
        $body['governance'] ?? null,
        $body['supersedes_linked_fact_id'] ?? null
    );

    echo json_encode([
        'status' => 'ok',
        'operation' => 'applyGovernedEventFact',
        'result' => $result,
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    api_error(400, $e->getMessage());
}