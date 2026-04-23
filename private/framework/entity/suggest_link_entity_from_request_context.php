<?php

declare(strict_types=1);

require_once __DIR__ . '/request_context.php';
require_once __DIR__ . '/suggest_link_entity_explicit_subject.php';

/**
 * Suggest how to link an entity using the latest subject from request context.
 *
 * Returns structured suggestion output only.
 * Performs no writes.
 *
 * @return array<string,mixed>
 *
 * @throws InvalidArgumentException
 * @throws RuntimeException
 */
function suggest_link_entity_from_request_context(
    PDO $pdo,
    string $rawLabel,
    string $entityTypeId,
    string $factTypeId
): array {
    $subjectEntityId = resolve_latest_subject_entity_id_from_request_context($pdo);

    return suggest_link_entity_explicit_subject(
        $pdo,
        $subjectEntityId,
        $rawLabel,
        $entityTypeId,
        $factTypeId
    );
}