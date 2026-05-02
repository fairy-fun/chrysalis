<?php

declare(strict_types=1);

require_once __DIR__ . '/../calendar/calendar_event_projection_target_guard.php';

function prose_projection_guard_target_if_needed(PDO $pdo, string $targetEntityId): void
{
    $targetEntityId = trim($targetEntityId);

    if (str_starts_with($targetEntityId, 'calendar_event:')) {
        require_calendar_event_projection_target_node($pdo, $targetEntityId);
    }
}

function insert_prose_projection(
    PDO $pdo,
    int $proseDraftId,
    string $projectionTypeId,
    string $targetEntityId,
    string $roleId,
    ?int $projectionOrder,
    int $isExportTarget
): int {
    prose_projection_guard_target_if_needed($pdo, $targetEntityId);

    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.prose_projections (
            prose_draft_id,
            projection_type_id,
            target_entity_id,
            role_id,
            projection_order,
            is_export_target
        ) VALUES (
            :prose_draft_id,
            :projection_type_id,
            :target_entity_id,
            :role_id,
            :projection_order,
            :is_export_target
        )
    ");

    $stmt->execute([
        ':prose_draft_id' => $proseDraftId,
        ':projection_type_id' => $projectionTypeId,
        ':target_entity_id' => trim($targetEntityId),
        ':role_id' => $roleId,
        ':projection_order' => $projectionOrder,
        ':is_export_target' => $isExportTarget,
    ]);

    return (int) $pdo->lastInsertId();
}

function clear_existing_export_target(
    PDO $pdo,
    string $projectionTypeId,
    string $targetEntityId
): void {
    prose_projection_guard_target_if_needed($pdo, $targetEntityId);

    $stmt = $pdo->prepare("
        UPDATE sxnzlfun_chrysalis.prose_projections
           SET is_export_target = 0
         WHERE projection_type_id = :projection_type_id
           AND target_entity_id = :target_entity_id
           AND is_export_target = 1
    ");

    $stmt->execute([
        ':projection_type_id' => $projectionTypeId,
        ':target_entity_id' => trim($targetEntityId),
    ]);
}