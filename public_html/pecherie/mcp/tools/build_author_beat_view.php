<?php

declare(strict_types=1);

// Variables in scope from index.php: $pdo, $id, $toolInput

$characterEntityId  = trim((string) ($toolInput['character_entity_id'] ?? ''));
$projectionEntityId = trim((string) ($toolInput['projection_entity_id'] ?? '')) ?: null;
$mode               = trim((string) ($toolInput['mode'] ?? ''));

if ($characterEntityId === '') {
    jsonrpcError($id, -32602, 'character_entity_id is required');
    exit;
}

if (!in_array($mode, ['READ_BASELINE', 'PROPOSE_FORWARD'], true)) {
    jsonrpcError($id, -32602, 'mode must be READ_BASELINE or PROPOSE_FORWARD');
    exit;
}

require_once __DIR__ . '/../../../../private/framework/expression/character_beat_label_suggester.php';
require_once __DIR__ . '/../../../../private/framework/expression/author_beat_view_builder.php';

try {
    $result = buildAuthorBeatView($pdo, $characterEntityId, $projectionEntityId, $mode);
} catch (Throwable $e) {
    jsonrpcError($id, -32603, 'buildAuthorBeatView error: ' . $e->getMessage());
    exit;
}

jsonrpcSuccess($id, [
    'content' => [
        [
            'type' => 'text',
            'text' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ],
    ],
]);