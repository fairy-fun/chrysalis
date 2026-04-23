<?php

declare(strict_types=1);

function resolve_latest_subject_entity_id_from_request_context(PDO $pdo): string
{
    $sql = <<<'SQL'
    SELECT rc.entity_id
    FROM sxnzlfun_chrysalis.request_context rc
    WHERE rc.entity_id IS NOT NULL
    ORDER BY rc.context_id DESC
    LIMIT 1
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $entityId = $stmt->fetchColumn();

    if (!is_string($entityId) || trim($entityId) === '') {
        throw new RuntimeException('No subject entity found in request_context');
    }

    return $entityId;
}