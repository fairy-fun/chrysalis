<?php

function require_calendar_projection_id(PDO $pdo, string $projectionEntityId): int
{
    $projectionEntityId = trim($projectionEntityId);

    if ($projectionEntityId === '') {
        throw new InvalidArgumentException('projection_entity_id must be non-empty');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM sxnzlfun_chrysalis.calendar_projections
        WHERE entity_id = :entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $projectionEntityId,
    ]);

    $id = $stmt->fetchColumn();

    if ($id === false || $id === null) {
        throw new RuntimeException("Projection not found for entity_id: {$projectionEntityId}");
    }

    return (int)$id;
}