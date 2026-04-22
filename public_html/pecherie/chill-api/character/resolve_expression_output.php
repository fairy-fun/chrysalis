<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/character/resolve_expression_output.php';

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function parse_required_string(mixed $value, string $field): string
{
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException("$field must be a non-empty string");
    }
    return trim($value);
}

function parse_optional_int(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    if (ctype_digit((string)$value)) return (int)$value;
    throw new InvalidArgumentException('Invalid integer parameter');
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        fail(405, 'Method not allowed');
    }

    $characterId = parse_required_string($_GET['character_id'] ?? null, 'character_id');

    $result = resolve_character_expression_output(
        makePdo(),
        $characterId,
        parse_optional_int($_GET['character_entity_id'] ?? null),
        parse_optional_int($_GET['domain_id'] ?? null),
        parse_optional_int($_GET['interlocutor_entity_id'] ?? null),
        parse_optional_int($_GET['social_context_id'] ?? null)
    );

    echo json_encode(['ok' => true, 'data' => $result]);

} catch (InvalidArgumentException $e) {
    fail(400, $e->getMessage());
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}