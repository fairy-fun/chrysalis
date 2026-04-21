<?php

declare(strict_types=1);

use JetBrains\PhpStorm\NoReturn;

header('Content-Type: application/json; charset=utf-8');

#[NoReturn]
function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

function validateTableName(string $table): bool
{
    return preg_match('/^[A-Za-z0-9_]+$/', $table) === 1;
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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$table = isset($_GET['table']) && is_string($_GET['table'])
    ? trim($_GET['table'])
    : '';

if ($table === '') {
    respond(400, ['error' => 'Missing required table parameter']);
}

if (!validateTableName($table)) {
    respond(400, ['error' => 'Invalid table parameter']);
}

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
    respond(500, [
        'error' => 'Database connection failed',
        'details' => $e->getMessage(),
    ]);
}

try {
    $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ((string) $activeDatabase !== $expectedDatabase) {
        respond(500, [
            'error' => 'Unexpected database selected',
            'details' => "Expected $expectedDatabase, got $activeDatabase",
        ]);
    }
} catch (Throwable $e) {
    respond(500, [
        'error' => 'Failed to verify active database',
        'details' => $e->getMessage(),
    ]);
}

try {
    $tableCheck = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = :database_name
           AND TABLE_NAME = :table_name'
    );

    $tableCheck->execute([
        ':database_name' => $expectedDatabase,
        ':table_name' => $table,
    ]);

    $tableExists = (int) $tableCheck->fetchColumn() > 0;

    if (!$tableExists) {
        respond(404, ['error' => 'Table not found']);
    }
} catch (Throwable $e) {
    respond(500, [
        'error' => 'Failed to verify table',
        'details' => $e->getMessage(),
    ]);
}

try {
    $stmt = $pdo->prepare(
        'SELECT
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_KEY,
            EXTRA
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :database_name
           AND TABLE_NAME = :table_name
         ORDER BY ORDINAL_POSITION'
    );

    $stmt->execute([
        ':database_name' => $expectedDatabase,
        ':table_name' => $table,
    ]);

    $rawColumns = $stmt->fetchAll();

    $columns = array_map(
        static function (array $column): array {
            return [
                'name' => (string) $column['COLUMN_NAME'],
                'type' => (string) $column['COLUMN_TYPE'],
                'nullable' => strtoupper((string) $column['IS_NULLABLE']) === 'YES',
                'key' => (string) $column['COLUMN_KEY'],
                'extra' => (string) $column['EXTRA'],
            ];
        },
        $rawColumns
    );

    respond(200, [
        'status' => 'ok',
        'source' => 'database',
        'database' => $expectedDatabase,
        'table' => $table,
        'columns' => $columns,
    ]);
} catch (Throwable $e) {
    respond(500, [
        'error' => 'Failed to fetch column definitions',
        'details' => $e->getMessage(),
    ]);
}