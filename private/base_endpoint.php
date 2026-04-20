<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Standard endpoint bootstrap.
 *
 * Returns:
 * - pdo   => PDO
 * - input => array
 */
function endpoint_bootstrap(string $method, string $scope = 'public'): array
{
    $method = strtoupper($method);

    require_method($method);
    enforce_api_key($scope);

    $pdo = db();
    $input = [];

    if ($method === 'GET') {
        $input = $_GET;
    } else {
        $input = $_POST;

        if (empty($input)) {
            $raw = file_get_contents('php://input');

            if ($raw !== false && trim($raw) !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                    if (is_array($decoded)) {
                        $input = $decoded;
                    }
                } catch (JsonException $e) {
                    // Leave $input as-is.
                    // Endpoint-level validation can decide whether missing fields are an error.
                }
            }
        }
    }

    return [
        'pdo'   => $pdo,
        'input' => $input,
    ];
}

function require_param(array $input, string $key): mixed
{
    if (!array_key_exists($key, $input)) {
        fail("missing_parameter: {$key}", 400);
    }

    return $input[$key];
}

function require_string_param(array $input, string $key): string
{
    $value = require_param($input, $key);

    if (!is_string($value)) {
        fail("invalid_parameter: {$key}", 400);
    }

    $value = trim($value);

    if ($value === '') {
        fail("invalid_parameter: {$key}", 400);
    }

    return $value;
}

function optional_string_param(array $input, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $input) || $input[$key] === null) {
        return $default;
    }

    if (!is_string($input[$key])) {
        fail("invalid_parameter: {$key}", 400);
    }

    return trim($input[$key]);
}

function optional_int(
    array $input,
    string $key,
    int $default,
    int $min = PHP_INT_MIN,
    int $max = PHP_INT_MAX
): int {
    if (!array_key_exists($key, $input) || $input[$key] === '' || $input[$key] === null) {
        return $default;
    }

    if (!is_numeric($input[$key])) {
        fail("invalid_parameter: {$key}", 400);
    }

    $value = (int) $input[$key];

    if ($value < $min || $value > $max) {
        fail("invalid_parameter: {$key}", 400);
    }

    return $value;
}

function log_api_error(Throwable $e): void
{
    $payload = [
        'type'     => 'api_error',
        'message'  => $e->getMessage(),
        'file'     => $e->getFile(),
        'line'     => $e->getLine(),
        'endpoint' => $_SERVER['SCRIPT_NAME'] ?? basename(__FILE__),
    ];

    try {
        error_log(json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    } catch (JsonException $jsonException) {
        error_log(sprintf(
            'api_error endpoint=%s message=%s file=%s line=%d',
            (string) ($payload['endpoint'] ?? 'unknown'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }
}