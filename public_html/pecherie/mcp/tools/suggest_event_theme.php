<?php

declare(strict_types=1);

// Variables in scope from index.php: $pdo, $id, $toolInput

$calendarEventEntityId = trim((string) ($toolInput['calendar_event_entity_id'] ?? ''));
$prose                 = trim((string) ($toolInput['prose'] ?? ''));

if ($calendarEventEntityId === '') {
    jsonrpcError($id, -32602, 'calendar_event_entity_id is required');
    exit;
}

if ($prose === '') {
    jsonrpcError($id, -32602, 'prose is required');
    exit;
}

// Resolve the handler the same way chill-api does
$handlerPath = __DIR__ . '/../../chill-api/expression/suggest_entity_event_theme_link.php';

if (!is_file($handlerPath)) {
    jsonrpcError($id, -32603, 'suggest_entity_event_theme_link handler not found');
    exit;
}

// Inject expected globals so the handler can read its inputs
$GLOBALS['_API_BODY'] = [
    'operation'               => 'suggestEntityEventThemeLink',
    'calendar_event_entity_id' => $calendarEventEntityId,
    'prose'                   => $prose,
];
$GLOBALS['_QUERY_BODY'] = $GLOBALS['_API_BODY'];

// Capture handler output
ob_start();
try {
    require $handlerPath;
} catch (Throwable $e) {
    ob_end_clean();
    jsonrpcError($id, -32603, 'Handler error: ' . $e->getMessage());
    exit;
}
$output = ob_get_clean();

$decoded = json_decode($output, true);
if (!is_array($decoded)) {
    jsonrpcError($id, -32603, 'Handler returned invalid JSON');
    exit;
}

jsonrpcSuccess($id, [
    'content' => [
        [
            'type' => 'text',
            'text' => json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ],
    ],
]);