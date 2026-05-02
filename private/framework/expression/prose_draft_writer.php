<?php


function createProseDraftWithProjection(
    PDO     $pdo,
    string  $title,
    string  $proseBody,
    ?string $authorEntityId,
    ?string $projectionTypeId,
    ?string $targetEntityId,
    ?string $roleId,
    ?int    $projectionOrder = null,
    bool    $isExportTarget = false,
    string  $draftStatusId = 'prose_status_draft'
): array
{
    if (trim($proseBody) === '') {
        return ['status' => 'error', 'message' => 'prose_body is required'];
    }

    if ($targetEntityId !== null && strpos($targetEntityId, ':') === false) {
        return ['status' => 'error', 'message' => 'target_entity_id must use canonical entity format'];
    }

    $pdo->beginTransaction();

    try {
        $tmpEntityId = 'prose_draft:tmp:' . bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.prose_drafts (
                entity_id,
                title,
                prose_body,
                draft_status_id,
                author_entity_id
            )
            VALUES (
                :entity_id,
                :title,
                :prose_body,
                :draft_status_id,
                :author_entity_id
            )
        ");

        $stmt->execute([
            ':entity_id' => $tmpEntityId,
            ':title' => $title,
            ':prose_body' => $proseBody,
            ':draft_status_id' => $draftStatusId,
            ':author_entity_id' => $authorEntityId,
        ]);

        $proseDraftId = (int)$pdo->lastInsertId();
        $proseEntityId = 'prose_draft:' . $proseDraftId;

        $stmt = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.entities (
                id,
                entity_type_id
            )
            VALUES (
                :entity_id,
                'entity_type_prose_draft'
            )
        ");

        $stmt->execute([
            ':entity_id' => $proseEntityId,
        ]);

        $stmt = $pdo->prepare("
            UPDATE sxnzlfun_chrysalis.prose_drafts
               SET entity_id = :entity_id
             WHERE id = :id
        ");

        $stmt->execute([
            ':entity_id' => $proseEntityId,
            ':id' => $proseDraftId,
        ]);

        $projectionId = null;

        if ($projectionTypeId !== null && $targetEntityId !== null && $roleId !== null) {

            // ✅ REQUIRED GUARD (add here)
            if (str_starts_with($targetEntityId, 'calendar_event:')) {
                require_calendar_event_projection_target_node($pdo, $targetEntityId);
            }

            if ($isExportTarget) {
                $stmt = $pdo->prepare("
                    UPDATE sxnzlfun_chrysalis.prose_projections
                       SET is_export_target = 0
                     WHERE projection_type_id = :projection_type_id
                       AND target_entity_id = :target_entity_id
                       AND is_export_target = 1
                ");

                $stmt->execute([
                    ':projection_type_id' => $projectionTypeId,
                    ':target_entity_id' => $targetEntityId,
                ]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO sxnzlfun_chrysalis.prose_projections (
                    prose_draft_id,
                    projection_type_id,
                    target_entity_id,
                    role_id,
                    projection_order,
                    is_export_target
                )
                VALUES (
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
                ':target_entity_id' => $targetEntityId,
                ':role_id' => $roleId,
                ':projection_order' => $projectionOrder,
                ':is_export_target' => $isExportTarget ? 1 : 0,
            ]);

            $projectionId = (int)$pdo->lastInsertId();
        }

        $pdo->commit();

        return [
            'status' => 'ok',
            'prose_draft_id' => $proseDraftId,
            'prose_entity_id' => $proseEntityId,
            'projection_id' => $projectionId,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();

        return [
            'status' => 'error',
            'message' => $e->getMessage(),
        ];
    }
}