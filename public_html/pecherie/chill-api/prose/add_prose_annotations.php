<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/prose/prose_draft_creator.php';

/**
 * Annotation augmentation contract.
 *
 * For person-targeted narrative tagging:
 *   - annotate the prose span
 *   - set subject_entity_id to the character/person entity id
 *   - use canonical entity-backed annotation_type_id / annotation_value_id ids
 *
 * This preserves later NL-oriented retrieval over both prose text and
 * character-linked annotation metadata.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $result = add_prose_annotations($pdo, $body);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'prose_entity_id' => $result['prose_entity_id'],
        'annotations' => $result['annotations'],
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
        'error' => 'Failed to add prose annotations',
        'database' => $expectedDatabase,
    ], $e);
}
