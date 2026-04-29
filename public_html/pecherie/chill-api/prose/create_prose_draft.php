<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/prose/prose_draft_creator.php';

function normalise_create_prose_draft_request(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    if (is_string($contentType) && stripos($contentType, 'multipart/form-data') !== false) {
        $body = $_POST;

        if (isset($body['projection']) && is_string($body['projection'])) {
            $projection = json_decode($body['projection'], true);

            if (!is_array($projection)) {
                throw new InvalidArgumentException('projection must be valid JSON when sent as multipart/form-data');
            }

            $body['projection'] = $projection;
        }

        if (isset($body['annotations']) && is_string($body['annotations']) && trim($body['annotations']) !== '') {
            $annotations = json_decode($body['annotations'], true);

            if (!is_array($annotations)) {
                throw new InvalidArgumentException('annotations must be valid JSON when sent as multipart/form-data');
            }

            $body['annotations'] = $annotations;
        }

        if (!array_key_exists('prose_body', $body) || trim((string) $body['prose_body']) === '') {
            $uploaded = $_FILES['prose_file'] ?? null;

            if (is_array($uploaded)) {
                $error = (int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE);

                if ($error !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('prose_file upload failed with error code ' . $error);
                }

                $tmpName = $uploaded['tmp_name'] ?? null;

                if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
                    throw new InvalidArgumentException('prose_file upload is invalid');
                }

                $contents = file_get_contents($tmpName);

                if (!is_string($contents) || trim($contents) === '') {
                    throw new InvalidArgumentException('prose_file must contain non-empty text');
                }

                $body['prose_body'] = $contents;
            }
        }

        foreach (['projection_order', 'is_export_target'] as $intField) {
            if (isset($body['projection'][$intField]) && is_string($body['projection'][$intField])) {
                if (!ctype_digit($body['projection'][$intField])) {
                    throw new InvalidArgumentException('projection.' . $intField . ' must be an integer');
                }

                $body['projection'][$intField] = (int) $body['projection'][$intField];
            }
        }

        if (array_key_exists('author_entity_id', $body) && $body['author_entity_id'] === '') {
            $body['author_entity_id'] = null;
        }

        return $body;
    }

    return getJsonBody();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

try {
    $body = normalise_create_prose_draft_request();
} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
    ]);
}

$pdo = makePdo('write');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $result = create_prose_draft($pdo, $body);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'prose' => $result['prose'],
        'projection' => $result['projection'],
    ]);

} catch (InvalidArgumentException $e) {
    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (RuntimeException $e) {
    respond(409, [
        'status' => 'error',
        'error' => $e->getMessage(),
        'database' => $expectedDatabase,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to create prose draft',
        'database' => $expectedDatabase,
    ], $e);
}
