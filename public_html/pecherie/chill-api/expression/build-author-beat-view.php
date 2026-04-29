<?php


require_once __DIR__ . '/../../../../private/bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/expression/author_beat_view_builder.php';

requireAuth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$characterEntityId = $input['character_entity_id']
    ?? $input['subject_entity_id']
    ?? null;

$projectionEntityId = $input['projection_entity_id'] ?? null;

$mode = $input['mode'] ?? 'READ_BASELINE';

if (!$characterEntityId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing character_entity_id or subject_entity_id',
    ]);
    exit;
}

$result = buildAuthorBeatView(
    $pdo,
    $characterEntityId,
    $projectionEntityId,
    $mode
);

echo json_encode($result);