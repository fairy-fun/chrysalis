<?php

require_once __DIR__ . '/../../../../private/bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/expression/character_next_beat_suggester.php';

requireAuth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$characterEntityId = $input['character_entity_id']
    ?? $input['subject_entity_id']
    ?? null;

$projectionEntityId = $input['projection_entity_id'] ?? null;

$currentThemeEntityId = $input['current_theme_entity_id'] ?? null;

if (!$characterEntityId || !$currentThemeEntityId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing character_entity_id/subject_entity_id or current_theme_entity_id',
    ]);
    exit;
}

$result = suggestNextCharacterBeat(
    $pdo,
    $characterEntityId,
    $projectionEntityId,
    $currentThemeEntityId
);

echo json_encode($result);