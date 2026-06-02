<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_draft_creator.php';

function applyProseAnnotationSpan(
    PDO $pdo,
    string $proseEntityId,
    ?string $subjectEntityId,
    string $annotationTypeId,
    string $annotationValueId,
    ?int $spanStart,
    ?int $spanEnd,
    string $sourceTypeId = 'annotation_source_curated'
): array {
    try {
        $proseBody = prose_body_for_entity($pdo, $proseEntityId);

        if ($proseBody === null) {
            return [
                'status' => 'error',
                'message' => 'Prose draft not found',
            ];
        }

        $validated = prose_validate_annotation(
            $pdo,
            [
                'subject_entity_id' => $subjectEntityId,
                'annotation_type_id' => $annotationTypeId,
                'annotation_value_id' => $annotationValueId,
                'span_start' => $spanStart,
                'span_end' => $spanEnd,
                'source_type_id' => $sourceTypeId,
            ],
            0,
            prose_body_length($proseBody)
        );
    } catch (InvalidArgumentException $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
        ];
    }

    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.prose_annotation_spans (
            prose_entity_id,
            subject_entity_id,
            annotation_type_id,
            annotation_value_id,
            span_start,
            span_end,
            source_type_id
        )
        VALUES (
            :prose_entity_id,
            :subject_entity_id,
            :annotation_type_id,
            :annotation_value_id,
            :span_start,
            :span_end,
            :source_type_id
        )
        ON DUPLICATE KEY UPDATE
            prose_entity_id = prose_entity_id
    ");

    $stmt->execute([
        ':prose_entity_id' => $proseEntityId,
        ':subject_entity_id' => $validated['subject_entity_id'],
        ':annotation_type_id' => $validated['annotation_type_id'],
        ':annotation_value_id' => $validated['annotation_value_id'],
        ':span_start' => $validated['span_start'],
        ':span_end' => $validated['span_end'],
        ':source_type_id' => $validated['source_type_id'],
    ]);

    return [
        'status' => 'ok',
        'annotation_span_id' => (int) $pdo->lastInsertId(),
        'prose_entity_id' => $proseEntityId,
    ];
}
