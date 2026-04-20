<?php

declare(strict_types=1);

/**
 * Global bootstrap for Chrysalis API.
 *
 * Responsibilities:
 * - load config
 * - provide PDO singleton
 * - enforce API key auth
 * - provide shared JSON/error helpers
 * - provide shared failure handling
 *
 * Compatibility note:
 * - fail() accepts both:
 *     fail(400, 'Message')
 *     fail('Message', 400)
 */

const APP_DEBUG = false;

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('UTC');

function app_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $config = require '/home/sxnzlfun/pecherie_config.php';

    if (!is_array($config)) {
        throw new RuntimeException('Invalid configuration file.');
    }

    return $config;
}

function db_config(): array
{
    $config = app_config();
    $db = $config['db'] ?? null;

    if (!is_array($db)) {
        throw new RuntimeException('Database configuration is missing.');
    }

    $host = trim((string) ($db['host'] ?? ''));
    $name = trim((string) ($db['name'] ?? ''));
    $user = trim((string) ($db['user'] ?? ''));
    $pass = (string) ($db['pass'] ?? '');
    $charset = trim((string) ($db['charset'] ?? 'utf8mb4'));

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('Database configuration is incomplete.');
    }

    return [
        'host' => $host,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'charset' => $charset !== '' ? $charset : 'utf8mb4',
    ];
}

function db_name(): string
{
    return db_config()['name'];
}

function public_api_key(): string
{
    $config = app_config();

    return trim((string) ($config['pecherie_api_key'] ?? ''));
}

function admin_api_key(): string
{
    $config = app_config();
    $adminKey = trim((string) ($config['pecherie_admin_api_key'] ?? ''));

    if ($adminKey !== '') {
        return $adminKey;
    }

    return public_api_key();
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = db_config();

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO(
        $dsn,
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

function request_headers_normalized(): array
{
    static $headers = null;

    if (is_array($headers)) {
        return $headers;
    }

    $headers = [];

    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = (string) $value;
        }
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            $headers[strtolower((string) $key)] = (string) $value;
        }
    }

    return $headers;
}

function request_api_key(): ?string
{
    $headers = request_headers_normalized();
    $value = $headers['x-api-key'] ?? null;

    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);

    return $value === '' ? null : $value;
}

function send_json_headers(int $status): void
{
    if (headers_sent($file, $line)) {
        error_log(sprintf(
            'Headers already sent before JSON response in %s on line %d',
            $file,
            $line
        ));
        return;
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
}

function json_response(array $data, int $status = 200): void
{
    send_json_headers($status);

    try {
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        send_json_headers(500);
        echo '{"status":"error","message":"Failed to encode JSON response."}';
    }

    exit;
}

/**
 * Compatible failure helper.
 *
 * Supported signatures:
 *   fail('Message')
 *   fail('Message', 400)
 *   fail(400, 'Message')
 *   fail('Message', 400, ['foo' => 'bar'])
 *   fail(400, 'Message', ['foo' => 'bar'])
 */
function fail(string|int $arg1, string|int $arg2 = 500, array $extra = []): void
{
    $status = 500;
    $message = 'Internal server error';

    if (is_int($arg1)) {
        $status = $arg1;
        $message = is_string($arg2) ? $arg2 : $message;
    } else {
        $message = $arg1;
        $status = is_int($arg2) ? $arg2 : 500;
    }

    json_response(
        array_merge(
            [
                'status' => 'error',
                'message' => $message,
            ],
            $extra
        ),
        $status
    );
}

function enforce_api_key(string $scope = 'public'): void
{
    $provided = request_api_key();

    if ($provided === null) {
        fail(401, 'Unauthorized');
    }

    $expected = match ($scope) {
        'public' => public_api_key(),
        'admin' => admin_api_key(),
        default => '',
    };

    if ($expected === '') {
        fail(500, 'Server auth is not configured');
    }

    if (!hash_equals($expected, $provided)) {
        fail(401, 'Unauthorized');
    }
}

function require_method(string $method): void
{
    $required = strtoupper($method);
    $actual = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));

    if ($actual !== $required) {
        if (!headers_sent()) {
            header('Allow: ' . $required);
        }

        fail(405, 'Method not allowed');
    }
}

set_exception_handler(function (Throwable $e): void {
    $message = APP_DEBUG ? $e->getMessage() : 'Internal server error';

    $extra = APP_DEBUG
        ? [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]
        : [];

    fail(500, $message, $extra);
});

set_error_handler(function (
    int $severity,
    string $message,
    string $file,
    int $line
): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});