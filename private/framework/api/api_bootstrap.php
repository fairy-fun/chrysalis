<?php

declare(strict_types=1);

const DEBUG_MODE = false;

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function debugRespond(int $statusCode, array $payload, ?Throwable $e = null): never
{
    if (DEBUG_MODE && $e !== null) {
        $payload['exception'] = get_class($e);
        $payload['message'] = $e->getMessage();
    }

    respond($statusCode, $payload);
}

function getConfig(): array
{
    $config = require __DIR__ . '/../../../pecherie_config.php';

    if (!is_array($config)) {
        respond(500, ['error' => 'Invalid server configuration']);
    }

    return $config;
}

function requireAuth(): void
{
    $config = getConfig();
    $expected = trim((string) ($config['pecherie_api_key'] ?? ''));

    if ($expected === '') {
        respond(500, ['error' => 'Server auth is not configured']);
    }

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

    if ($provided === null || !hash_equals($expected, $provided)) {
        respond(401, ['error' => 'Unauthorized']);
    }
}

function getJsonBody(): array
{
    if (array_key_exists('_API_BODY', $GLOBALS) && is_array($GLOBALS['_API_BODY'])) {
        return $GLOBALS['_API_BODY'];
    }

    if (array_key_exists('_QUERY_BODY', $GLOBALS) && is_array($GLOBALS['_QUERY_BODY'])) {
        return $GLOBALS['_QUERY_BODY'];
    }

    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        $GLOBALS['_API_BODY'] = [];
        $GLOBALS['_QUERY_BODY'] = [];
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        respond(400, ['error' => 'Request body must be valid JSON']);
    }

    $GLOBALS['_API_BODY'] = $decoded;
    $GLOBALS['_QUERY_BODY'] = $decoded;

    return $decoded;
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

function makePdo(): PDO
{
    $db = getDatabaseConfig();

    try {
        return new PDO(
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
        debugRespond(500, ['error' => 'Database connection failed'], $e);
    }
}

function verifyExpectedDatabase(PDO $pdo): string
{
    $db = getDatabaseConfig();
    $expectedDatabase = $db['name'];

    try {
        $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable $e) {
        debugRespond(500, ['error' => 'Failed to verify active database'], $e);
    }

    if ((string) $activeDatabase !== $expectedDatabase) {
        respond(500, [
            'error' => 'Unexpected database selected',
            'details' => 'Expected ' . $expectedDatabase . ', got ' . (string) $activeDatabase,
        ]);
    }

    return $expectedDatabase;
}