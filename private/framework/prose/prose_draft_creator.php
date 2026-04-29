<?php

declare(strict_types=1);

function prose_required_string(array $source, string $key): string
{
    $value = $source[$key] ?? null;

    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException($key . ' must be a non-empty string');
    }

    return trim($value);
}

function prose_optional_string_or_null(array $source, string $key): ?string
{
    if (!array_key_exists($key, $source) || $source[$key] === null) {
        return null;
    }

    if (!is_string($source[$key]) || trim($source[$key]) === '') {
        throw new InvalidArgumentException($key . ' must be null or a non-empty string');
    }

    return trim($source[$key]);
}

function prose_required_positive_int(array $source, string $key): int
{
    $value = $source[$key] ?? null;

    if (!is_int($value) || $value < 1) {
        throw new InvalidArgumentException($key . ' must be a positive integer');
    }

    return $value;
}

function prose_required_boolean_int(array $source, string $key): int
{
    $value = $source[$key] ?? null;

    if ($value !== 0 && $value !== 1) {
        throw new InvalidArgumentException($key . ' must be 0 or 1');
    }

    return $value;
}

function prose_entity_exists(PDO $pdo, string $entityId): bool
{
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM sxnzlfun_chrysalis.entities\n        WHERE entity_id = :entity_id\n    ");

    $stmt->execute([':entity_id' => $entityId]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_classval_exists(PDO $pdo, string $classvalId): bool
{
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM sxnzlfun_chrysalis.classvals\n        WHERE id = :id\n    ");

    $stmt->execute([':id' => $classvalId]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_calendar_target_exists(PDO $pdo, string $targetEntityId): bool
{
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM sxnzlfun_chrysalis.calendar_events\n        WHERE entity_id = :entity_id\n    ");

    $stmt->execute([':entity_id' => $targetEntityId]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_draft_entity_exists(PDO $pdo, string $entityId): bool
{
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM sxnzlfun_chrysalis.prose_drafts\n        WHERE entity_id = :entity_id\n    ");

    $stmt->execute([':entity_id' => $entityId]);

    return (int) $stmt->fetchColumn() > 0;
}


function prose_export_target_exists(
    PDO $pdo,
    string $projectionTypeId,
    string $targetEntityId
): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.prose_projections
        WHERE projection_type_id = :projection_type_id
          AND target_entity_id = :target_entity_id
          AND is_export_target = 1
    ");

    $stmt->execute([
        ':projection_type_id' => $projectionTypeId,
        ':target_entity_id' => $targetEntityId,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function create_prose_draft(PDO $pdo, array $body): array
{
    $entityId = prose_required_string($body, 'entity_id');
    $title = prose_required_string($body, 'title');
    $proseBody = prose_required_string($body, 'prose_body');
    $draftStatusId = prose_required_string($body, 'draft_status_id');
    $authorEntityId = prose_optional_string_or_null($body, 'author_entity_id');

    $projection = $body['projection'] ?? null;

    if (!is_array($projection)) {
        throw new InvalidArgumentException('projection must be present');
    }

    $projectionTypeId = prose_required_string($projection, 'projection_type_id');
    $targetEntityId = prose_required_string($projection, 'target_entity_id');
    $roleId = prose_required_string($projection, 'role_id');
    $projectionOrder = prose_required_positive_int($projection, 'projection_order');
    $isExportTarget = prose_required_boolean_int($projection, 'is_export_target');

    if ($isExportTarget === 1 && prose_export_target_exists($pdo, $projectionTypeId, $targetEntityId)) {
        throw new RuntimeException(
            'Export target already exists for projection_type_id=' . $projectionTypeId .
            ' and target_entity_id=' . $targetEntityId
        );
    }

    if (prose_draft_entity_exists($pdo, $entityId)) {
        throw new RuntimeException('Duplicate prose draft entity_id: ' . $entityId);
    }

    if (!prose_classval_exists($pdo, $draftStatusId)) {
        throw new InvalidArgumentException('Invalid draft_status_id: ' . $draftStatusId);
    }

    if (!prose_classval_exists($pdo, $projectionTypeId)) {
        throw new InvalidArgumentException('Invalid projection_type_id: ' . $projectionTypeId);
    }

    if (!prose_classval_exists($pdo, $roleId)) {
        throw new InvalidArgumentException('Invalid projection.role_id: ' . $roleId);
    }

    if (!prose_calendar_target_exists($pdo, $targetEntityId)) {
        throw new InvalidArgumentException('Invalid projection.target_entity_id: ' . $targetEntityId);
    }

    if ($authorEntityId !== null && !prose_entity_exists($pdo, $authorEntityId)) {
        throw new InvalidArgumentException('Invalid author_entity_id: ' . $authorEntityId);
    }

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $insertDraft = $pdo->prepare("\n            INSERT INTO sxnzlfun_chrysalis.prose_drafts (\n                entity_id,\n                title,\n                prose_body,\n                draft_status_id,\n                author_entity_id\n            ) VALUES (\n                :entity_id,\n                :title,\n                :prose_body,\n                :draft_status_id,\n                :author_entity_id\n            )\n        ");

        $insertDraft->execute([
            ':entity_id' => $entityId,
            ':title' => $title,
            ':prose_body' => $proseBody,
            ':draft_status_id' => $draftStatusId,
            ':author_entity_id' => $authorEntityId,
        ]);

        $proseDraftId = (int) $pdo->lastInsertId();

        if ($proseDraftId < 1) {
            throw new RuntimeException('Failed to create prose draft');
        }

        $insertProjection = $pdo->prepare("\n            INSERT INTO sxnzlfun_chrysalis.prose_projections (\n                prose_draft_id,\n                projection_type_id,\n                target_entity_id,\n                role_id,\n                projection_order,\n                is_export_target\n            ) VALUES (\n                :prose_draft_id,\n                :projection_type_id,\n                :target_entity_id,\n                :role_id,\n                :projection_order,\n                :is_export_target\n            )\n        ");

        $insertProjection->execute([
            ':prose_draft_id' => $proseDraftId,
            ':projection_type_id' => $projectionTypeId,
            ':target_entity_id' => $targetEntityId,
            ':role_id' => $roleId,
            ':projection_order' => $projectionOrder,
            ':is_export_target' => $isExportTarget,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'prose' => [
                'entity_id' => $entityId,
                'title' => $title,
            ],
            'projection' => [
                'target_entity_id' => $targetEntityId,
                'projection_type_id' => $projectionTypeId,
                'role_id' => $roleId,
            ],
        ];

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
