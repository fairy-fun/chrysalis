<?php

declare(strict_types=1);

require_once __DIR__ . '/../calendar/calendar_event_projection_target_guard.php';
require_once __DIR__ . '/../dreams/dream_journal_projection_target_guard.php';

/**
 * Guard only when target is a calendar event.
 */
function prose_projection_guard_target_if_needed(PDO $pdo, string $targetEntityId): void
{
    $targetEntityId = trim($targetEntityId);

    if ($targetEntityId === '') {
        throw new InvalidArgumentException('target_entity_id must be non-empty');
    }

    if (
        !str_starts_with($targetEntityId, 'calendar_event:') &&
        !str_starts_with($targetEntityId, 'dream_journal:')
    ) {
        throw new InvalidArgumentException('Unsupported target_entity_id domain');
    }

    // Calendar domain
    if (str_starts_with($targetEntityId, 'calendar_event:')) {
        require_calendar_event_projection_target_node($pdo, $targetEntityId);
        return;
    }

    // Dream journal domain
    if (str_starts_with($targetEntityId, 'dream_journal:')) {
        require_dream_journal_projection_target_entity($pdo, $targetEntityId);
        return;
    }
}
/**
 * Ensures the dream journal target exists and is the correct entity type.
 */
function require_dream_journal_projection_target_entity(PDO $pdo, string $entityId): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :id
          AND entity_type_id = 'dream_journal'
    ");

    $stmt->execute([':id' => $entityId]);

    if ((int)$stmt->fetchColumn() !== 1) {
        throw new InvalidArgumentException(
            'Invalid dream_journal target_entity_id: ' . $entityId
        );
    }
}

/**
 * Insert projection row.
 *
 * This is the ONLY allowed write path into prose_projections.
 */
function insert_prose_projection(
    PDO $pdo,
    int $proseDraftId,
    string $projectionTypeId,
    string $targetEntityId,
    string $roleId,
    ?int $projectionOrder,
    int $isExportTarget
): int {
    $projectionTypeId = trim($projectionTypeId);
    $targetEntityId   = trim($targetEntityId);
    $roleId           = trim($roleId);

    if ($projectionTypeId === '') {
        throw new InvalidArgumentException('projection_type_id must be non-empty');
    }

    if ($roleId === '') {
        throw new InvalidArgumentException('role_id must be non-empty');
    }

    if ($projectionOrder !== null && $projectionOrder < 1) {
        throw new InvalidArgumentException('projection_order must be null or positive');
    }

    if ($isExportTarget !== 0 && $isExportTarget !== 1) {
        throw new InvalidArgumentException('is_export_target must be 0 or 1');
    }

    // 🔒 Guard calendar targets only
    prose_projection_guard_target_if_needed($pdo, $targetEntityId);

    // 🔁 Ensure single export target per (type, target)
    if ($isExportTarget === 1) {
        clear_existing_export_target($pdo, $projectionTypeId, $targetEntityId);
    }

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
        ':prose_draft_id'    => $proseDraftId,
        ':projection_type_id'=> $projectionTypeId,
        ':target_entity_id'  => $targetEntityId,
        ':role_id'           => $roleId,
        ':projection_order'  => $projectionOrder,
        ':is_export_target'  => $isExportTarget,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Clears existing export target for (projection_type_id, target_entity_id)
 */
function clear_existing_export_target(
    PDO $pdo,
    string $projectionTypeId,
    string $targetEntityId
): void {
    $projectionTypeId = trim($projectionTypeId);
    $targetEntityId   = trim($targetEntityId);

    if ($projectionTypeId === '' || $targetEntityId === '') {
        throw new InvalidArgumentException('projection_type_id and target_entity_id must be non-empty');
    }

    $stmt = $pdo->prepare("
        UPDATE sxnzlfun_chrysalis.prose_projections
           SET is_export_target = 0
         WHERE projection_type_id = :projection_type_id
           AND target_entity_id   = :target_entity_id
           AND is_export_target   = 1
    ");

    $stmt->execute([
        ':projection_type_id' => $projectionTypeId,
        ':target_entity_id'   => $targetEntityId,
    ]);
}