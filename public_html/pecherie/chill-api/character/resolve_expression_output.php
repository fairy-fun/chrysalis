<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/expression/expression_output_resolver.php';

const EXPRESSION_AUTHORITY_HANDLER =
    'public_html/pecherie/chill-api/expression/resolve_character_expression_output.php';

function fail(int $status, string $message): never
{
    respond($status, [
        'status' => 'error',
        'ok' => false,
        'error' => $message,
        'deprecated_alias' => true,
        'authority_handler' => EXPRESSION_AUTHORITY_HANDLER,
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

    throw new InvalidArgumentException('Invalid integer parameter');
}

function parse_optional_compat_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && trim($value) !== '' && ctype_digit(trim($value)) && (int) trim($value) > 0) {
        return (int) trim($value);
    }

    throw new InvalidArgumentException('Invalid integer parameter');
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method !== 'GET') {
        fail(405, 'Method not allowed');
    }

    requireAuth();

    $input = $_GET;
    $characterId = parse_required_string($input['character_id'] ?? null, 'character_id');
    $domainId = parse_optional_domain_id($input['domain_id'] ?? null);
    $characterEntityId = parse_optional_compat_int($input['character_entity_id'] ?? null);
    $interlocutorEntityId = parse_optional_compat_int($input['interlocutor_entity_id'] ?? null);
    $socialContextId = parse_optional_compat_int($input['social_context_id'] ?? null);
    $pdo = makePdo();
    $expectedDatabase = verifyExpectedDatabase($pdo);

    $result = resolve_character_expression_output($pdo, $characterId, $domainId);

    $compatData = [
        'context' => [
            'character_id' => $characterId,
            'domain_id' => $domainId === null ? null : (int) $domainId,
            'character_entity_id' => $characterEntityId,
            'interlocutor_entity_id' => $interlocutorEntityId,
            'social_context_id' => $socialContextId,
        ],
        'resolved_output' => $result['resolved_output'] ?? [
            'layer_voice' => [],
            'layer_psych' => [],
            'layer_limbic' => [],
        ],
        'override_rules' => [],
        'surface_directives' => [],
    ];

    respond(200, [
        'status' => 'ok',
        'ok' => true,
        'database' => $expectedDatabase,
        'character_id' => $characterId,
        'domain_id' => $domainId,
        'output' => $result,
        'data' => $compatData,
        'deprecated_alias' => true,
        'authority_handler' => EXPRESSION_AUTHORITY_HANDLER,
    ]);

} catch (InvalidArgumentException $e) {
    fail(400, $e->getMessage());
} catch (RuntimeException $e) {
    fail(404, $e->getMessage());
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}
