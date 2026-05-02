<?php

declare(strict_types=1);

require_once __DIR__ . '/../calendar/calendar_event_projection_target_guard.php';
require_once __DIR__ . '/../dreams/dream_journal_projection_target_guard.php';

/**
 * Guard supported projection target domains.
 */
function prose_projection_guard_target_if_needed(PDO $pdo, string $targetEntityId): void
{
    $targetEntityId = trim($targetEntityId);

    if ($targetEntityId === '') {
        throw new InvalidArgumentException('target_entity_id must be non-empty');
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
 * Insert projection row (idempotent + strict conflict handling).
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

    // 🔒 Guard domain validity
    prose_projection_guard_target_if_needed($pdo, $targetEntityId);

    /**
     * ✅ Idempotent insert (identity enforced by UNIQUE index)
     * ❗ Does NOT mutate existing rows
     */
    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.prose_projections (
            prose_draft_id,
            projection_type_id,
            target_entity_id,
            role_id,
            projection_order,
            is_export_target,
            created_at
        ) VALUES (
            :prose_draft_id,
            :projection_type_id,
            :target_entity_id,
            :role_id,
            :projection_order,
            :is_export_target,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            projection_order = projection_order,
            is_export_target = is_export_target
    ");

    try {
        $stmt->execute([
            ':prose_draft_id'     => $proseDraftId,
            ':projection_type_id' => $projectionTypeId,
            ':target_entity_id'   => $targetEntityId,
            ':role_id'            => $roleId,
            ':projection_order'   => $projectionOrder,
            ':is_export_target'   => $isExportTarget,
        ]);
    } catch (PDOException $e) {

        // 🔴 Handle strict export-target conflict
        if ($e->errorInfo[1] === 1062 && $isExportTarget === 1) {

            throw new RuntimeException(
                "Export target conflict for {$projectionTypeId} → {$targetEntityId}"
            );
        }

        throw $e;
    }

    /**
     * ✅ Resolve row ID (works for insert OR duplicate)
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM sxnzlfun_chrysalis.prose_projections
        WHERE prose_draft_id = :prose_draft_id
          AND projection_type_id = :projection_type_id
          AND target_entity_id = :target_entity_id
          AND role_id = :role_id
        LIMIT 1
    ");

    $stmt->execute([
        ':prose_draft_id'     => $proseDraftId,
        ':projection_type_id' => $projectionTypeId,
        ':target_entity_id'   => $targetEntityId,
        ':role_id'            => $roleId,
    ]);

    $id = (int)$stmt->fetchColumn();

    if ($id < 1) {
        throw new RuntimeException('Failed to resolve prose projection');
    }

    return $id;
}