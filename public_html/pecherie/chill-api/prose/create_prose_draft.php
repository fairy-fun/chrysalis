<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/prose/prose_draft_creator.php';


/**
 *
 *
 * Prose-first ingestion contract.
 *
 * Canonical authorial payload:
 *   - prose_body
 *
 * Optional editorial overrides:
 *   - title
 *   - summary
 *
 * Structural metadata such as beat summaries,
 * projection recommendations, chronology inference,
 * and export recommendations are derived by the system.
 */

function normalise_projection_payload(array $body): array
{
    if (!isset($body['projection']) || !is_array($body['projection'])) {
        return $body;
    }

    $projection = $body['projection'];

    /*
     * Legacy payloads:
     *
     * projection_type_id = projection_type_book
     *
     * New contract:
     *
     * projection_classval_id = projection_type_book
     * projection_type_id      = book1
     */

    if (
        !isset($projection['projection_classval_id'])
        && isset($projection['projection_type_id'])
        && is_string($projection['projection_type_id'])
        && str_starts_with($projection['projection_type_id'], 'projection_type_')
    ) {
        $projection['projection_classval_id'] = $projection['projection_type_id'];

        /*
         * Transitional resolver
         *
         * Current projection_id already exists in payload.
         * Resolve concrete topology projection_type_id from DB.
         */

        if (
            isset($projection['projection_id'])
            && is_int($projection['projection_id'])
        ) {
            $pdo = makePdo('read');

            $stmt = $pdo->prepare("
                SELECT projection_type_id
                FROM sxnzlfun_chrysalis.calendar_projections
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $projection['projection_id'],
            ]);

            $resolved = $stmt->fetchColumn();

            if (is_string($resolved) && trim($resolved) !== '') {
                $projection['projection_type_id'] = trim($resolved);
            }
        }
    }

    $body['projection'] = $projection;

    return $body;
}

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

        foreach (['projection_order'] as $intField) {
            if (isset($body['projection'][$intField]) && is_string($body['projection'][$intField])) {
                if (!ctype_digit($body['projection'][$intField])) {
                    throw new InvalidArgumentException('projection.' . $intField . ' must be an integer');
                }

                $body['projection'][$intField] = (int) $body['projection'][$intField];
            }
        }

        if (isset($body['annotations']) && is_array($body['annotations'])) {
            foreach ($body['annotations'] as $index => $annotation) {
                if (!is_array($annotation)) {
                    throw new InvalidArgumentException('annotations[' . $index . '] must be an object');
                }

                foreach (['span_start', 'span_end'] as $spanField) {
                    if (
                        isset($body['annotations'][$index][$spanField])
                        && is_string($body['annotations'][$index][$spanField])
                    ) {
                        if (!ctype_digit($body['annotations'][$index][$spanField])) {
                            throw new InvalidArgumentException('annotations[' . $index . '].' . $spanField . ' must be an integer');
                        }

                        $body['annotations'][$index][$spanField] = (int) $body['annotations'][$index][$spanField];
                    }
                }

                if (
                    array_key_exists('subject_entity_id', $body['annotations'][$index])
                    && $body['annotations'][$index]['subject_entity_id'] === ''
                ) {
                    $body['annotations'][$index]['subject_entity_id'] = null;
                }

                foreach (['span_start', 'span_end'] as $spanField) {
                    if (
                        array_key_exists($spanField, $body['annotations'][$index])
                        && $body['annotations'][$index][$spanField] === ''
                    ) {
                        $body['annotations'][$index][$spanField] = null;
                    }
                }
            }
        }

        if (array_key_exists('author_entity_id', $body) && $body['author_entity_id'] === '') {
            $body['author_entity_id'] = null;
        }

        return normalise_projection_payload($body);
    }

    return normalise_projection_payload(getJsonBody());
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
        'annotations' => $result['annotations'],
        'calendar' => $result['calendar'] ?? null,
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