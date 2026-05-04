<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../private/bootstrap.php';

// ─── CORS ─────────────────────────────────────────────────────────────────────

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-api-key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ─── Auth ─────────────────────────────────────────────────────────────────────

enforce_api_key('public');

// ─── Anthropic API key ────────────────────────────────────────────────────────

$config       = app_config();
$anthropicKey = trim((string) ($config['anthropic_api_key'] ?? ''));

if ($anthropicKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Anthropic API key not configured']);
    exit;
}

// ─── Parse and validate incoming body ────────────────────────────────────────

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// ─── Build Anthropic request ──────────────────────────────────────────────────

$payload = [
    'model'      => $body['model']      ?? 'claude-sonnet-4-20250514',
    'max_tokens' => $body['max_tokens'] ?? 4096,
    'messages'   => $body['messages']   ?? [],
];

// Optional fields — pass through if present
foreach (['system', 'tools', 'tool_choice', 'temperature', 'stream'] as $field) {
    if (isset($body[$field])) {
        $payload[$field] = $body[$field];
    }
}

// ─── Always inject the Chrysalis MCP server ───────────────────────────────────

$payload['mcp_servers'] = [
    [
        'type' => 'url',
        'url'  => 'https://mcp.antheapeche.com/',
        'name' => 'chrysalis',
    ],
];

// ─── Forward to Anthropic ─────────────────────────────────────────────────────

$ch = curl_init('https://api.anthropic.com/v1/messages');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $anthropicKey,
        'anthropic-version: 2023-06-01',
        'anthropic-beta: mcp-client-2025-11-20',
    ],
    CURLOPT_TIMEOUT        => 60,
]);

$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream request failed', 'detail' => $curlErr]);
    exit;
}

http_response_code($status);
header('Content-Type: application/json; charset=utf-8');
echo $response;

