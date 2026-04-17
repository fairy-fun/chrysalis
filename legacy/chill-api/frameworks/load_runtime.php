<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getConfig(): array
{
    $config = require dirname(__DIR__, 2) . '/config/bootstrap.php';

    if (!is_array($config)) {
        respond(500, ['error' => 'Invalid server configuration']);
    }

    return $config;
}

function requireAuth(): void
{
    $config = getConfig();
    $expected = trim((string) ($config['pecherie_api_key'] ?? ''));

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided = null;

    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === 'x-api-key') {
            $provided = trim((string) $value);
            break;
        }
    }

    if ($provided === null && isset($_SERVER['HTTP_X_API_KEY'])) {
        $provided = trim((string) $_SERVER['HTTP_X_API_KEY']);
    }

    if ($expected === '') {
        respond(500, ['error' => 'Server auth is not configured']);
    }

    if ($provided === null || !hash_equals($expected, $provided)) {
        respond(401, ['error' => 'Unauthorized']);
    }
}

function getDatabaseConfig(): array
{
    $config = getConfig();

    if (!isset($config['db']) || !is_array($config['db'])) {
        respond(500, ['error' => 'Database configuration is missing']);
    }

    $db = $config['db'];

    $host = trim((string) ($db['host'] ?? ''));
    $name = trim((string) ($db['name'] ?? ''));
    $user = trim((string) ($db['user'] ?? ''));
    $pass = (string) ($db['pass'] ?? '');
    $charset = trim((string) ($db['charset'] ?? 'utf8mb4'));

    if ($host === '' || $name === '' || $user === '') {
        respond(500, ['error' => 'Database configuration is incomplete']);
    }

    return [
        'host' => $host,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'charset' => $charset !== '' ? $charset : 'utf8mb4',
    ];
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        respond(400, ['error' => 'Request body must be valid JSON']);
    }

    return $decoded;
}

/*
|--------------------------------------------------------------------------
| Request validation
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$body = getJsonBody();

/*
|--------------------------------------------------------------------------
| Scope validation (STRICT – verified allowed set)
|--------------------------------------------------------------------------
*/

$allowedScopes = [
    'framework_scope_general',
    'framework_scope_character',
    'framework_scope_calendar',
    'framework_scope_relationship'
];

$scope = isset($body['scope']) && is_string($body['scope'])
    ? trim($body['scope'])
    : '';

if ($scope === '' || !in_array($scope, $allowedScopes, true)) {
    respond(400, ['error' => 'Invalid or missing scope']);
}

/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
*/

$db = getDatabaseConfig();
$expectedDatabase = $db['name'];

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $e) {
    respond(500, ['error' => 'Database connection failed']);
}

try {
    $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ((string) $activeDatabase !== $expectedDatabase) {
        respond(500, [
            'error' => 'Unexpected database selected',
            'details' => "Expected {$expectedDatabase}, got {$activeDatabase}",
        ]);
    }
} catch (Throwable $e) {
    respond(500, ['error' => 'Failed to verify active database']);
}

/*
|--------------------------------------------------------------------------
| Runtime framework loader query
|--------------------------------------------------------------------------
*/

$sql = "
SELECT
    requested_scope_id,
    framework_id,
    framework_name,
    framework_scope_id,
    rule_priority,
    rule_type_id,
    rule_phase_id,
    applies_to_sql_operation_id,
    constraint_rule,
    usage_rule,
    framework_structure_json,
    effective_layer_rank,
    effective_layer_priority
FROM sxnzlfun_chrysalis.v_framework_runtime_loader
WHERE requested_scope_id = :scope
ORDER BY
    effective_layer_rank ASC,
    effective_layer_priority ASC,
    rule_priority ASC,
    framework_name ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['scope' => $scope]);
    $rows = $stmt->fetchAll();

    respond(200, [
        'requested_scope_id' => $scope,
        'framework_count' => count($rows),
        'frameworks' => $rows
    ]);
} catch (Throwable $e) {
    respond(500, [
        'error' => 'Query failed',
        'database' => $expectedDatabase,
    ]);
}
