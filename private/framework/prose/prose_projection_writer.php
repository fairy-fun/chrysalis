<?php

declare(strict_types=1);

require_once __DIR__ . '/../calendar/calendar_event_projection_target_guard.php';
require_once __DIR__ . '/../dreams/dream_journal_projection_target_guard.php';

/**
 * Guard supported projection target domains.
 */
function prose_projection_guard_target_if_needed(
    PDO $pdo,
    string $targetEntityId
): void {

    $targetEntityId = trim($targetEntityId);

    if ($targetEntityId === '') {
        throw new InvalidArgumentException(
            'target_entity_id must be non-empty'
        );
    }

    // Calendar domain
    if (str_starts_with($targetEntityId, 'calendar_event:')) {
        require_calendar_event_projection_target_node(
            $pdo,
            $targetEntityId
        );

        return;
    }

    // Dream journal domain
    if (str_starts_with($targetEntityId, 'dream_journal:')) {
        require_dream_journal_projection_target_entity(
            $pdo,
            $targetEntityId
        );

        return;
    }
}

/**
 * Verify that the draft selected for publication belongs to the family
 * that the projection places.
 */
function prose_projection_published_draft_belongs_to_family(
    PDO $pdo,
    int $proseFamilyId,
    int $publishedProseDraftId
): bool {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.prose_drafts
        WHERE id = :published_prose_draft_id
          AND prose_family_id = :prose_family_id
    ");

    $stmt->execute([
        ':published_prose_draft_id' => $publishedProseDraftId,
        ':prose_family_id' => $proseFamilyId,
    ]);

    return (int) $stmt->fetchColumn() === 1;
}

/**
 * Insert projection row.
 *
 * Canonical ontology:
 *
 * projection -> prose family
 * projection -> published draft
 *
 * prose_family_id
 *     = topology attachment
 *
 * published_prose_draft_id
 *     = projection-local publication selection
 *
 */
function insert_prose_projection(
    PDO $pdo,
    int $proseFamilyId,
    int $publishedProseDraftId,
    string $projectionClassvalId,
    string $projectionTypeId,
    string $targetEntityId,
    string $roleId,
    ?int $projectionOrder,
): int {

    $projectionClassvalId = trim($projectionClassvalId);
    $projectionTypeId = trim($projectionTypeId);
    $targetEntityId = trim($targetEntityId);
    $roleId = trim($roleId);

    if ($proseFamilyId < 1) {
        throw new InvalidArgumentException(
            'proseFamilyId must be positive'
        );
    }

    if ($publishedProseDraftId < 1) {
        throw new InvalidArgumentException(
            'publishedProseDraftId must be positive'
        );
    }

    if ($projectionClassvalId === '') {
        throw new InvalidArgumentException(
            'projection_classval_id must be non-empty'
        );
    }

    if ($projectionTypeId === '') {
        throw new InvalidArgumentException(
            'projection_type_id must be non-empty'
        );
    }

    if ($targetEntityId === '') {
        throw new InvalidArgumentException(
            'target_entity_id must be non-empty'
        );
    }

    if ($roleId === '') {
        throw new InvalidArgumentException(
            'role_id must be non-empty'
        );
    }

    if ($projectionOrder !== null && $projectionOrder < 1) {
        throw new InvalidArgumentException(
            'projection_order must be null or positive'
        );
    }

    if (
        !prose_projection_published_draft_belongs_to_family(
            $pdo,
            $proseFamilyId,
            $publishedProseDraftId
        )
    ) {
        throw new InvalidArgumentException(
            'publishedProseDraftId must belong to proseFamilyId'
        );
    }

    prose_projection_guard_target_if_needed(
        $pdo,
        $targetEntityId
    );

    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.prose_projections (
        prose_family_id,
        published_prose_draft_id,
        projection_classval_id,
        projection_type_id,
        target_entity_id,
        role_id,
        projection_order,
        created_at
    ) VALUES (
        :prose_family_id,
        :published_prose_draft_id,
        :projection_classval_id,
        :projection_type_id,
        :target_entity_id,
        :role_id,
        :projection_order,
        NOW()
    )
");

    try {

        $stmt->execute([
            ':prose_family_id' => $proseFamilyId,
            ':published_prose_draft_id' => $publishedProseDraftId,
            ':projection_classval_id' => $projectionClassvalId,
            ':projection_type_id' => $projectionTypeId,
            ':target_entity_id' => $targetEntityId,
            ':role_id' => $roleId,
            ':projection_order' => $projectionOrder,
        ]);

    } catch (PDOException $e) {

        if (
            isset($e->errorInfo[1])
            && (int) $e->errorInfo[1] === 1062
        ) {
            throw new RuntimeException(
                'Duplicate prose projection identity for '
                . $projectionTypeId
                . ' -> '
                . $targetEntityId
            );
        }

        throw $e;
    }

    /**
     * Resolve projection row ID by canonical topology identity.
     *
     * projection_order is intentionally not part of identity here:
     * order is placement metadata, while identity is family + projection
     * class/type + target + role.
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM sxnzlfun_chrysalis.prose_projections
        WHERE prose_family_id = :prose_family_id
          AND projection_classval_id = :projection_classval_id
          AND projection_type_id = :projection_type_id
          AND target_entity_id = :target_entity_id
          AND role_id = :role_id
        LIMIT 1
    ");

    $stmt->execute([
        ':prose_family_id' => $proseFamilyId,
        ':projection_classval_id' => $projectionClassvalId,
        ':projection_type_id' => $projectionTypeId,
        ':target_entity_id' => $targetEntityId,
        ':role_id' => $roleId,
    ]);

    $id = (int) $stmt->fetchColumn();

    if ($id < 1) {
        throw new RuntimeException(
            'Failed to resolve prose projection'
        );
    }

    return $id;
}
