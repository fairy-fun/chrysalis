<?php

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
    if ($spanStart !== null && $spanEnd !== null && $spanEnd < $spanStart) {
        return [
            'status' => 'error',
            'message' => 'span_end must be greater than or equal to span_start',
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
    ");

    $stmt->execute([
        ':prose_entity_id' => $proseEntityId,
        ':subject_entity_id' => $subjectEntityId,
        ':annotation_type_id' => $annotationTypeId,
        ':annotation_value_id' => $annotationValueId,
        ':span_start' => $spanStart,
        ':span_end' => $spanEnd,
        ':source_type_id' => $sourceTypeId,
    ]);

    return [
        'status' => 'ok',
        'annotation_span_id' => (int) $pdo->lastInsertId(),
        'prose_entity_id' => $proseEntityId,
    ];
}