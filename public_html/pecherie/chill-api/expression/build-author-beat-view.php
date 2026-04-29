<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../private/bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/expression/author_beat_view_builder.php';

require_method('POST');
enforce_api_key('public');

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    fail(400, 'Request body must be valid JSON');
}

$characterEntityId = $input['character_entity_id']
    ?? $input['subject_entity_id']
    ?? null;

$projectionEntityId = $input['projection_entity_id'] ?? null;
$mode = $input['mode'] ?? 'READ_BASELINE';

if (!is_string($characterEntityId) || $characterEntityId === '') {
    fail(400, 'Missing character_entity_id or subject_entity_id');
}

if ($projectionEntityId !== null && !is_string($projectionEntityId)) {
    fail(400, 'projection_entity_id must be a string or null');
}

if (!is_string($mode) || $mode === '') {
    fail(400, 'mode must be a string');
}

$result = buildAuthorBeatView(
    db(),
    $characterEntityId,
    $projectionEntityId,
    $mode
);

json_response($result);