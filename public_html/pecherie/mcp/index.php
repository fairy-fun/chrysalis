<?php

declare(strict_types=1);

// ─── Configuration ────────────────────────────────────────────────────────────

const AUTH0_DOMAIN   = 'antheapeche7.us.auth0.com';
const AUTH0_AUDIENCE = 'https://antheapeche.com/pecherie/mcp/';
const MCP_SERVER_URL = 'https://antheapeche.com/pecherie/mcp/';
const MCP_VERSION    = '2025-06-18';

// ─── Bootstrap ───────────────────────────────────────────────────────────────

require_once __DIR__ . '/../../../private/bootstrap.php';

// ─── CORS ────────────────────────────────────────────────────────────────────

header('Access-Control-Allow-Origin: https://claude.ai');
header('Access-Control-Allow-Methods: GET, POST, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Mcp-Session-Id');
header('Access-Control-Expose-Headers: Mcp-Session-Id, MCP-Protocol-Version');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── HEAD — protocol discovery ───────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    header('MCP-Protocol-Version: ' . MCP_VERSION);
    http_response_code(200);
    exit;
}

// ─── GET — only allowed for OAuth proxy routes (handled below) ───────────────
// Non-proxy GETs are rejected after route matching

// ─── OAuth discovery endpoints ───────────────────────────────────────────────

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = rtrim((string) $requestUri, '/');

// ─── /authorize proxy — redirect to Auth0 ───────────────────────────────────

if (str_ends_with($requestUri, '/authorize')) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    // Force correct audience so Auth0 issues a token scoped to our API
    if (strpos($query, 'audience=') === false) {
        $query .= ($query !== '' ? '&' : '') . 'audience=' . urlencode(AUTH0_AUDIENCE);
    }
    header('Location: https://' . AUTH0_DOMAIN . '/authorize?' . $query, true, 302);
    exit;
}

// ─── /token proxy — forward to Auth0 and relay response ─────────────────────

if (str_ends_with($requestUri, '/token') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", [
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen((string) $body),
            ]),
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents(
        'https://' . AUTH0_DOMAIN . '/oauth/token',
        false,
        $context
    );

    // Relay Auth0 response headers that matter
    foreach ($http_response_header ?? [] as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            header($header);
        }
        if (stripos($header, 'HTTP/') === 0) {
            $parts = explode(' ', $header, 3);
            http_response_code((int) ($parts[1] ?? 200));
        }
    }

    echo $response !== false ? $response : json_encode(['error' => 'token_proxy_failed']);
    exit;
}

if (str_ends_with($requestUri, '/.well-known/oauth-protected-resource')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'resource'                => MCP_SERVER_URL,
        'authorization_servers'  => ['https://' . AUTH0_DOMAIN],
        'bearer_methods_supported' => ['header'],
        'scopes_supported'        => ['openid', 'profile', 'email'],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if (str_ends_with($requestUri, '/.well-known/oauth-authorization-server')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'issuer'                                => 'https://' . AUTH0_DOMAIN . '/',
        'authorization_endpoint'               => 'https://' . AUTH0_DOMAIN . '/authorize',
        'token_endpoint'                        => 'https://' . AUTH0_DOMAIN . '/oauth/token',
        'registration_endpoint'                => 'https://' . AUTH0_DOMAIN . '/oidc/register',
        'jwks_uri'                              => 'https://' . AUTH0_DOMAIN . '/.well-known/jwks.json',
        'response_types_supported'             => ['code'],
        'grant_types_supported'                => ['authorization_code', 'refresh_token'],
        'code_challenge_methods_supported'     => ['S256'],
        'token_endpoint_auth_methods_supported' => ['none'],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── Only POST from here (non-proxy GETs rejected) ───────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Allow: POST, HEAD, OPTIONS');
    http_response_code(405);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ─── Session management ───────────────────────────────────────────────────────

$sessionId = $_SERVER['HTTP_MCP_SESSION_ID'] ?? null;
if ($sessionId === null) {
    $sessionId = bin2hex(random_bytes(16));
}
header('Mcp-Session-Id: ' . $sessionId);
header('MCP-Protocol-Version: ' . MCP_VERSION);

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

// ─── OAuth token validation ───────────────────────────────────────────────────

function validateBearerToken(): bool
{
    $headers  = function_exists('getallheaders') ? getallheaders() : [];
    $provided = null;

    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === 'authorization') {
            $provided = trim(preg_replace('/^Bearer\s+/i', '', trim((string) $value)));
            break;
        }
    }

    if ($provided === null && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $provided = trim(preg_replace('/^Bearer\s+/i', '', trim((string) $_SERVER['HTTP_AUTHORIZATION'])));
    }

    if ($provided === null || $provided === '') {
        return false;
    }

    // Fetch JWKS from Auth0
    $jwksUrl = 'https://' . AUTH0_DOMAIN . '/.well-known/jwks.json';
    $jwks    = @file_get_contents($jwksUrl);
    if ($jwks === false) {
        return false;
    }

    $jwksData = json_decode($jwks, true);
    if (!is_array($jwksData) || !isset($jwksData['keys'])) {
        return false;
    }

    // Decode JWT header to find kid
    $parts = explode('.', $provided);
    if (count($parts) !== 3) {
        return false;
    }

    $headerDecoded = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    $kid = $headerDecoded['kid'] ?? null;

    // Find matching key
    $matchingKey = null;
    foreach ($jwksData['keys'] as $key) {
        if ($kid === null || ($key['kid'] ?? null) === $kid) {
            $matchingKey = $key;
            break;
        }
    }

    if ($matchingKey === null) {
        return false;
    }

    // Build public key from JWK
    if (!isset($matchingKey['n'], $matchingKey['e'])) {
        return false;
    }

    $modulus  = base64_decode(strtr($matchingKey['n'], '-_', '+/'));
    $exponent = base64_decode(strtr($matchingKey['e'], '-_', '+/'));

    // Verify signature using openssl
    $publicKey = openssl_pkey_get_public([
        'n' => $modulus,
        'e' => $exponent,
    ]);

    // Use userinfo endpoint as fallback if openssl approach isn't straightforward
    // This is the most reliable approach for Auth0 RS256 tokens in PHP without a JWT library
    $userinfoUrl = 'https://' . AUTH0_DOMAIN . '/userinfo';
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => 'Authorization: Bearer ' . $provided . "\r\n",
            'timeout' => 5,
        ],
    ]);

    $userinfo = @file_get_contents($userinfoUrl, false, $context);
    if ($userinfo === false) {
        return false;
    }

    $userinfoData = json_decode($userinfo, true);

    // Valid if we got a sub claim back
    return is_array($userinfoData) && isset($userinfoData['sub']);
}

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
                'character_entity_id'  => ['type' => 'string', 'description' => 'Entity ID of the character (e.g. CHAR-MAIN-001)'],
                'projection_entity_id' => ['type' => 'string', 'description' => 'Optional projection entity ID to filter events'],
                'mode'                 => ['type' => 'string', 'enum' => ['READ_BASELINE', 'PROPOSE_FORWARD'], 'description' => 'READ_BASELINE returns tagged beats; PROPOSE_FORWARD returns suggestions'],
            ],
            'required' => ['character_entity_id', 'mode'],
        ],
    ],
];

// ─── Request parsing ──────────────────────────────────────────────────────────

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

// ─── Methods that do NOT require auth ────────────────────────────────────────

switch ($method) {

    case 'initialize':
        jsonrpcSuccess($id, [
            'protocolVersion' => MCP_VERSION,
            'capabilities'    => ['tools' => []],
            'serverInfo'      => [
                'name'    => 'chrysalis-mcp',
                'version' => '1.0.0',
            ],
        ]);
        exit;

    case 'notifications/initialized':
        http_response_code(202);
        exit;
}

// ─── All other methods require a valid Bearer token ───────────────────────────

if (!validateBearerToken()) {
    http_response_code(401);
    header('WWW-Authenticate: Bearer realm="' . MCP_SERVER_URL . '"');
    jsonrpcError($id, -32600, 'Unauthorized');
    exit;
}

// ─── Authenticated method dispatch ───────────────────────────────────────────

switch ($method) {

    case 'tools/list':
        jsonrpcSuccess($id, ['tools' => MCP_TOOLS]);
        break;

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