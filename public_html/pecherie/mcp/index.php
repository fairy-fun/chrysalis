<?php

declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────────────────────────

require_once __DIR__ . '/../../../private/bootstrap.php';

// ─── CORS ────────────────────────────────────────────────────────────────────

header('Access-Control-Allow-Origin: https://claude.ai');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-api-key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ─── Auth ────────────────────────────────────────────────────────────────────

function requireMcpAuth(): void
{
    $config  = getConfig();
    $expected = trim((string) ($config['mcp_api_key'] ?? ''));

    if ($expected === '') {
        http_response_code(500);
        echo json_encode(['error' => 'MCP auth is not configured']);
        exit;
    }

    $headers  = function_exists('getallheaders') ? getallheaders() : [];
    $provided = null;

    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === 'authorization') {
            // Accept both "Bearer <token>" and raw token
            $provided = trim(preg_replace('/^Bearer\s+/i', '', trim((string) $value)));
            break;
        }
    }

    if ($provided === null && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $provided = trim(preg_replace('/^Bearer\s+/i', '', trim((string) $_SERVER['HTTP_AUTHORIZATION'])));
    }

    if ($provided === null || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

requireMcpAuth();

// ─── MCP Tool Registry ───────────────────────────────────────────────────────

const MCP_TOOLS = [
    [
        'name'        => 'query_database',
        'description' => 'Execute a read-only SQL SELECT query against sxnzlfun_chrysalis. Returns rows as JSON. Use fully qualified table names.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'sql'   => ['type' => 'string', 'description' => 'A read-only SELECT statement against sxnzlfun_chrysalis'],
                'limit' => ['type' => 'integer', 'description' => 'Max rows to return (default 100, max 500)', 'default' => 100],
            ],
            'required' => ['sql'],
        ],
    ],
    [
        'name'        => 'suggest_event_theme',
        'description' => 'Suggest narrative theme links for a calendar event entity based on its prose summary. Returns candidate theme entity IDs and beat labels.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'calendar_event_entity_id' => ['type' => 'string', 'description' => 'The entity ID of the calendar event'],
                'prose'                    => ['type' => 'string', 'description' => 'The prose or summary text of the event to analyse'],
            ],
            'required' => ['calendar_event_entity_id', 'prose'],
        ],
    ],
    [
        'name'        => 'build_author_beat_view',
        'description' => 'Build the beat sequence view for a character — either READ_BASELINE (observed tagged beats) or PROPOSE_FORWARD (suggested next beats).',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'character_entity_id'    => ['type' => 'string', 'description' => 'Entity ID of the character (e.g. CHAR-MAIN-001)'],
                'projection_entity_id'   => ['type' => 'string', 'description' => 'Optional projection entity ID to filter events'],
                'mode'                   => ['type' => 'string', 'enum' => ['READ_BASELINE', 'PROPOSE_FORWARD'], 'description' => 'READ_BASELINE returns tagged beats; PROPOSE_FORWARD returns suggestions'],
            ],
            'required' => ['character_entity_id', 'mode'],
        ],
    ],
];

// ─── JSON-RPC helpers ────────────────────────────────────────────────────────

function jsonrpcSuccess(mixed $id, mixed $result): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => $result,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function jsonrpcError(mixed $id, int $code, string $message): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'jsonrpc' => '2.0',
        'id'      => $id,
        'error'   => ['code' => $code, 'message' => $message],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// ─── Request parsing ─────────────────────────────────────────────────────────

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    jsonrpcError(null, -32700, 'Parse error: empty body');
    exit;
}

$rpc = json_decode($raw, true);
if (!is_array($rpc)) {
    jsonrpcError(null, -32700, 'Parse error: invalid JSON');
    exit;
}

$id     = $rpc['id'] ?? null;
$method = $rpc['method'] ?? '';
$params = $rpc['params'] ?? [];

// ─── Method dispatch ─────────────────────────────────────────────────────────

switch ($method) {

    // MCP handshake
    case 'initialize':
        jsonrpcSuccess($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => ['tools' => []],
            'serverInfo'      => [
                'name'    => 'chrysalis-mcp',
                'version' => '1.0.0',
            ],
        ]);
        break;

    case 'notifications/initialized':
        // No response required for notifications
        http_response_code(204);
        break;

    // Tool list
    case 'tools/list':
        jsonrpcSuccess($id, ['tools' => MCP_TOOLS]);
        break;

    // Tool call
    case 'tools/call':
        $toolName  = $params['name'] ?? '';
        $toolInput = $params['arguments'] ?? [];

        try {
            $pdo = makePdo();
            verifyExpectedDatabase($pdo);
        } catch (Throwable $e) {
            jsonrpcError($id, -32603, 'Database connection failed: ' . $e->getMessage());
            exit;
        }

        switch ($toolName) {
            case 'query_database':
                require __DIR__ . '/tools/query_database.php';
                break;

            case 'suggest_event_theme':
                require __DIR__ . '/tools/suggest_event_theme.php';
                break;

            case 'build_author_beat_view':
                require __DIR__ . '/tools/build_author_beat_view.php';
                break;

            default:
                jsonrpcError($id, -32601, 'Unknown tool: ' . $toolName);
        }
        break;

    default:
        jsonrpcError($id, -32601, 'Method not found: ' . $method);
}