<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/character/resolve_character_appearance.php';

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function parse_required_string(mixed $value, string $field): string
{
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException("$field must be a non-empty string");
    }

    return trim($value);
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method !== 'GET' && $method !== 'POST') {
        fail(405, 'Method not allowed');
    }

    requireAuth();

    $input = $method === 'POST' ? getJsonBody() : $_GET;
    $characterId = parse_required_string($input['character_id'] ?? null, 'character_id');

    $pdo = makePdo('read');
    verifyExpectedDatabase($pdo);

    echo json_encode(
        [
            'ok' => true,
            'data' => [
                'character_id' => $characterId,
                'appearance' => resolve_character_appearance($pdo, $characterId),
            ],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (InvalidArgumentException $e) {
    fail(400, $e->getMessage());
} catch (RuntimeException $e) {
    fail(404, $e->getMessage());
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}
