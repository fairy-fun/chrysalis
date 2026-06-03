<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/expression/expression_output_resolver.php';

function fail(int $status, string $message): never
{
    respond($status, [
        'status' => 'error',
        'error' => $message,
        'deprecated_alias' => true,
        'authority_handler' => 'public_html/pecherie/chill-api/expression/resolve_character_expression_output.php',
    ]);
}

function parse_required_string(mixed $value, string $field): string
{
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException("$field must be a non-empty string");
    }
    return trim($value);
}

function parse_optional_domain_id(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_int($value) && $value > 0) {
        return (string) $value;
    }

    if (is_string($value) && trim($value) !== '' && ctype_digit(trim($value)) && (int) trim($value) > 0) {
        return trim($value);
    }

    throw new InvalidArgumentException('domain_id must be a positive integer when provided');
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method !== 'GET' && $method !== 'POST') {
        fail(405, 'Method not allowed');
    }

    $input = $method === 'POST' ? getJsonBody() : $_GET;
    $characterId = parse_required_string($input['character_id'] ?? null, 'character_id');
    $domainId = parse_optional_domain_id($input['domain_id'] ?? null);
    $pdo = makePdo();
    $expectedDatabase = verifyExpectedDatabase($pdo);

    $result = resolve_character_expression_output($pdo, $characterId, $domainId);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'character_id' => $characterId,
        'domain_id' => $domainId,
        'output' => $result,
        'deprecated_alias' => true,
        'authority_handler' => 'public_html/pecherie/chill-api/expression/resolve_character_expression_output.php',
    ]);

} catch (InvalidArgumentException $e) {
    fail(400, $e->getMessage());
} catch (RuntimeException $e) {
    fail(404, $e->getMessage());
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}
