<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/character/resolve_knowledge_packet.php';

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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        fail(405, 'Method not allowed');
    }

    requireAuth();

    $characterId = parse_required_string($_GET['character_id'] ?? null, 'character_id');

    $pdo = makePdo('read');
    verifyExpectedDatabase($pdo);

    $result = resolve_character_knowledge_packet($pdo, $characterId);

    echo json_encode(
        [
            'ok' => true,
            'data' => $result,
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
