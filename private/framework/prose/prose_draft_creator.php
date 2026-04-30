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

function prose_required_zero_or_positive_int(array $source, string $key): int
{
    $value = $source[$key] ?? null;

    if (!is_int($value) || $value < 0) {
        throw new InvalidArgumentException($key . ' must be a zero-or-positive integer');
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
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :id
    ");

    $stmt->execute([':id' => $entityId]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_entity_exists_for_type(PDO $pdo, string $entityId, string $entityTypeId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :id
          AND entity_type_id = :entity_type_id
    ");

    $stmt->execute([
        ':id' => $entityId,
        ':entity_type_id' => $entityTypeId,
    ]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_annotation_value_is_valid_for_type(
    PDO $pdo,
    string $annotationTypeId,
    string $annotationValueId
): bool {
    $allowedEntityTypeByAnnotationType = [
        'prose_annotation_type_character_presence' => 'entity_type_character',
        'prose_annotation_type_theme_observation' => 'entity_type_theme',
    ];

    if (!array_key_exists($annotationTypeId, $allowedEntityTypeByAnnotationType)) {
        return prose_entity_exists($pdo, $annotationValueId);
    }

    return prose_entity_exists_for_type(
        $pdo,
        $annotationValueId,
        $allowedEntityTypeByAnnotationType[$annotationTypeId]
    );
}

function prose_classval_exists(PDO $pdo, string $classvalId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.classvals
        WHERE id = :id
    ");

    $stmt->execute([':id' => $classvalId]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_classval_exists_for_type(PDO $pdo, string $classvalId, string $classvalTypeId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.classvals
        WHERE id = :id
          AND classval_type_id = :classval_type_id
    ");

    $stmt->execute([
        ':id' => $classvalId,
        ':classval_type_id' => $classvalTypeId,
    ]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_calendar_target_exists(PDO $pdo, string $targetEntityId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE entity_id = :entity_id
    ");

    $stmt->execute([':entity_id' => $targetEntityId]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_draft_entity_exists(PDO $pdo, string $entityId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.prose_drafts
        WHERE entity_id = :entity_id
    ");

    $stmt->execute([':entity_id' => $entityId]);

    return (int) $stmt->fetchColumn() > 0;
}

function prose_body_for_entity(PDO $pdo, string $entityId): ?string
{
    $stmt = $pdo->prepare("
        SELECT prose_body
        FROM sxnzlfun_chrysalis.prose_drafts
        WHERE entity_id = :entity_id
        LIMIT 1
    ");

    $stmt->execute([':entity_id' => $entityId]);

    $body = $stmt->fetchColumn();

    return is_string($body) ? $body : null;
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

function prose_normalise_annotations(array $body): array
{
    if (!array_key_exists('annotations', $body) || $body['annotations'] === null) {
        return [];
    }

    if (!is_array($body['annotations'])) {
        throw new InvalidArgumentException('annotations must be an array');
    }

    return $body['annotations'];
}

function prose_body_length(string $proseBody): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($proseBody, 'UTF-8');
    }

    return strlen($proseBody);
}

function prose_validate_annotation(
    PDO $pdo,
    array $annotation,
    int $index,
    int $proseLength
): array {
    $prefix = 'annotations[' . $index . '].';

    $subjectEntityId = prose_optional_string_or_null($annotation, 'subject_entity_id');
    $annotationTypeId = prose_required_string($annotation, 'annotation_type_id');
    $annotationValueId = prose_required_string($annotation, 'annotation_value_id');
    $sourceTypeId = prose_required_string($annotation, 'source_type_id');

    $hasSpanStart = array_key_exists('span_start', $annotation) && $annotation['span_start'] !== null;
    $hasSpanEnd = array_key_exists('span_end', $annotation) && $annotation['span_end'] !== null;

    if ($hasSpanStart !== $hasSpanEnd) {
        throw new InvalidArgumentException($prefix . 'span_start and span_end must both be supplied or both be null');
    }

    $spanStart = null;
    $spanEnd = null;

    if ($hasSpanStart && $hasSpanEnd) {
        $spanStart = prose_required_zero_or_positive_int($annotation, 'span_start');
        $spanEnd = prose_required_zero_or_positive_int($annotation, 'span_end');

        if ($spanEnd <= $spanStart) {
            throw new InvalidArgumentException($prefix . 'span_end must be greater than span_start');
        }

        if ($spanEnd > $proseLength) {
            throw new InvalidArgumentException($prefix . 'span_end exceeds prose_body length');
        }
    }

    if (!prose_classval_exists_for_type($pdo, $annotationTypeId, 'cvt_prose_annotation_type')) {
        throw new InvalidArgumentException($prefix . 'invalid annotation_type_id: ' . $annotationTypeId);
    }

    if (!prose_classval_exists_for_type($pdo, $sourceTypeId, 'cvt_prose_annotation_source_type')) {
        throw new InvalidArgumentException($prefix . 'invalid source_type_id: ' . $sourceTypeId);
    }

    if (!prose_annotation_value_is_valid_for_type($pdo, $annotationTypeId, $annotationValueId)) {
        throw new InvalidArgumentException(
            $prefix . 'invalid annotation_value_id for annotation_type_id=' .
            $annotationTypeId . ': ' . $annotationValueId
        );
    }

    if ($subjectEntityId !== null && !prose_entity_exists($pdo, $subjectEntityId)) {
        throw new InvalidArgumentException($prefix . 'invalid subject_entity_id: ' . $subjectEntityId);
    }

    return [
        'subject_entity_id' => $subjectEntityId,
        'annotation_type_id' => $annotationTypeId,
        'annotation_value_id' => $annotationValueId,
        'span_start' => $spanStart,
        'span_end' => $spanEnd,
        'source_type_id' => $sourceTypeId,
    ];
}

function prose_validate_annotations(PDO $pdo, array $annotations, string $proseBody): array
{
    $validated = [];
    $proseLength = prose_body_length($proseBody);

    foreach ($annotations as $index => $annotation) {
        if (!is_array($annotation)) {
            throw new InvalidArgumentException('annotations[' . $index . '] must be an object');
        }

        $validated[] = prose_validate_annotation($pdo, $annotation, (int) $index, $proseLength);
    }

    return $validated;
}

function insert_prose_annotations(
    PDO $pdo,
    string $proseEntityId,
    array $annotations
): int {
    if ($annotations === []) {
        return 0;
    }

    $insert = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.prose_annotation_spans (
            prose_entity_id,
            subject_entity_id,
            annotation_type_id,
            annotation_value_id,
            span_start,
            span_end,
            source_type_id
        ) VALUES (
            :prose_entity_id,
            :subject_entity_id,
            :annotation_type_id,
            :annotation_value_id,
            :span_start,
            :span_end,
            :source_type_id
        )
    ");

    $inserted = 0;

    foreach ($annotations as $annotation) {
        $insert->execute([
            ':prose_entity_id' => $proseEntityId,
            ':subject_entity_id' => $annotation['subject_entity_id'],
            ':annotation_type_id' => $annotation['annotation_type_id'],
            ':annotation_value_id' => $annotation['annotation_value_id'],
            ':span_start' => $annotation['span_start'],
            ':span_end' => $annotation['span_end'],
            ':source_type_id' => $annotation['source_type_id'],
        ]);

        $inserted++;
    }

    return $inserted;
}

function add_prose_annotations(PDO $pdo, array $body): array
{
    $entityId = prose_required_string($body, 'entity_id');

    $proseBody = prose_body_for_entity($pdo, $entityId);

    if ($proseBody === null) {
        throw new InvalidArgumentException('Prose not found: ' . $entityId);
    }

    $annotations = prose_normalise_annotations($body);

    if ($annotations === []) {
        throw new InvalidArgumentException('annotations must contain at least one annotation');
    }

    $validatedAnnotations = prose_validate_annotations($pdo, $annotations, $proseBody);

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $insertedAnnotations = insert_prose_annotations($pdo, $entityId, $validatedAnnotations);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'prose_entity_id' => $entityId,
            'annotations' => [
                'inserted' => $insertedAnnotations,
            ],
        ];

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
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

    $annotations = prose_normalise_annotations($body);

    if ($isExportTarget === 1 && prose_export_target_exists($pdo, $projectionTypeId, $targetEntityId)) {
        throw new RuntimeException(
            'Export target already exists for projection_type_id=' . $projectionTypeId .
            ' and target_entity_id=' . $targetEntityId
        );
    }

    if (prose_draft_entity_exists($pdo, $entityId)) {
        throw new RuntimeException('Duplicate prose draft entity_id: ' . $entityId);
    }

    if (!prose_entity_exists_for_type($pdo, $draftStatusId, 'entity_type_status')) {
        throw new InvalidArgumentException('Invalid draft_status_id: ' . $draftStatusId);
    }

    if (!prose_classval_exists($pdo, $projectionTypeId)) {
        throw new InvalidArgumentException('Invalid projection_type_id: ' . $projectionTypeId);
    }

    /*if (!prose_classval_exists($pdo, $roleId)) {
        throw new InvalidArgumentException('Invalid projection.role_id: ' . $roleId);
    }*/

    if (!prose_calendar_target_exists($pdo, $targetEntityId)) {
        throw new InvalidArgumentException('Invalid projection.target_entity_id: ' . $targetEntityId);
    }

    if ($authorEntityId !== null && !prose_entity_exists($pdo, $authorEntityId)) {
        throw new InvalidArgumentException('Invalid author_entity_id: ' . $authorEntityId);
    }

    $validatedAnnotations = prose_validate_annotations($pdo, $annotations, $proseBody);

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $insertDraft = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.prose_drafts (
                entity_id,
                title,
                prose_body,
                draft_status_id,
                author_entity_id
            ) VALUES (
                :entity_id,
                :title,
                :prose_body,
                :draft_status_id,
                :author_entity_id
            )
        ");

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

        $insertProjection = $pdo->prepare("
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

        $insertProjection->execute([
            ':prose_draft_id' => $proseDraftId,
            ':projection_type_id' => $projectionTypeId,
            ':target_entity_id' => $targetEntityId,
            ':role_id' => $roleId,
            ':projection_order' => $projectionOrder,
            ':is_export_target' => $isExportTarget,
        ]);

        $insertedAnnotations = insert_prose_annotations($pdo, $entityId, $validatedAnnotations);

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
            'annotations' => [
                'inserted' => $insertedAnnotations,
            ],
        ];

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}