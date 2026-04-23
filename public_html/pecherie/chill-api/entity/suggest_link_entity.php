<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/entity/entity_link_suggestions.php';

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function parse_required_string(mixed $value, string $field): string
{
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException($field . ' must be a non-empty string');
    }

    return trim($value);
}

function parse_optional_string(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    if (!is_string($value)) {
        throw new InvalidArgumentException('Invalid string parameter');
    }

    $value = trim($value);

    return $value === '' ? null : $value;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail(405, 'Method not allowed');
    }

    requireAuth();

    $body = getJsonBody();

    $rawLabel = parse_required_string($body['raw_label'] ?? null, 'raw_label');
    $entityTypeId = parse_required_string($body['entity_type_id'] ?? null, 'entity_type_id');
    $factTypeId = parse_required_string($body['fact_type_id'] ?? null, 'fact_type_id');
    $subjectEntityId = parse_optional_string($body['subject_entity_id'] ?? null);

    $pdo = makePdo();

    if ($subjectEntityId === null) {
        throw new InvalidArgumentException('subject_entity_id must be a non-empty string');
    }

    $suggestion = suggest_link_entity_explicit_subject(
        $pdo,
        $subjectEntityId,
        $rawLabel,
        $entityTypeId,
        $factTypeId
    );

    echo json_encode(
        [
            'ok' => true,
            'data' => $suggestion,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (InvalidArgumentException $e) {
    fail(400, $e->getMessage());
} catch (RuntimeException $e) {
    fail(500, $e->getMessage());
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}