<?php

function prose_training_fetch_entity_summaries(PDO $pdo, array $entityIds): array
{
    $entityIds = array_values(array_unique(array_filter(
        $entityIds,
        static fn ($value): bool => is_string($value) && trim($value) !== ''
    )));

    if ($entityIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($entityIds as $index => $entityId) {
        $placeholder = ':entity_id_' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $entityId;
    }

    $sql = "
        SELECT
            e.id,
            e.entity_type_id,
            c.classval_type_id,
            c.code AS classval_code,
            c.label AS classval_label,
            MIN(et.canonical_label) AS canonical_label
        FROM sxnzlfun_chrysalis.entities e
        LEFT JOIN sxnzlfun_chrysalis.classvals c
            ON c.id = e.id
        LEFT JOIN sxnzlfun_chrysalis.entity_texts et
            ON et.entity_id = e.id
        WHERE e.id IN (" . implode(', ', $placeholders) . ")
        GROUP BY
            e.id,
            e.entity_type_id,
            c.classval_type_id,
            c.code,
            c.label
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $summaries = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $summaries[$row['id']] = [
            'id' => $row['id'],
            'entity_type_id' => $row['entity_type_id'],
            'canonical_label' => $row['canonical_label'],
            'classval_type_id' => $row['classval_type_id'],
            'classval_code' => $row['classval_code'],
            'classval_label' => $row['classval_label'],
        ];
    }

    return $summaries;
}

function prose_training_annotation_bucket_key(array $typeEntity): ?string
{
    $candidates = [
        $typeEntity['canonical_label'] ?? null,
        $typeEntity['classval_label'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        $normalized = strtolower(trim($candidate));

        if ($normalized === 'voice') {
            return 'voice';
        }

        if ($normalized === 'limbic') {
            return 'limbic';
        }

        if ($normalized === 'expression') {
            return 'expression';
        }

        if ($normalized === 'theme') {
            return 'theme';
        }
    }

    return null;
}

function prose_training_annotation_value_label(?array $valueEntity, string $fallbackId): string
{
    foreach ([
        $valueEntity['canonical_label'] ?? null,
        $valueEntity['classval_label'] ?? null,
    ] as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return $fallbackId;
}

function buildProseTrainingView(
    PDO $pdo,
    string $proseEntityId
): array {
    $stmt = $pdo->prepare("
        SELECT
            pd.id,
            pd.entity_id,
            pd.title,
            pd.prose_body,
            pd.draft_status_id,
            pd.author_entity_id,
            pd.created_at,
            pd.updated_at
        FROM sxnzlfun_chrysalis.prose_drafts pd
        WHERE pd.entity_id = :prose_entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':prose_entity_id' => $proseEntityId,
    ]);

    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft) {
        return [
            'status' => 'error',
            'message' => 'Prose draft not found',
            'prose_entity_id' => $proseEntityId,
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            pp.id,
            pp.projection_type_id,
            pp.target_entity_id,
            pp.role_id,
            pp.projection_order,
            pp.is_export_target,
            pp.created_at
        FROM sxnzlfun_chrysalis.prose_projections pp
        WHERE pp.prose_draft_id = :prose_draft_id
        ORDER BY
            pp.projection_type_id,
            pp.target_entity_id,
            pp.projection_order,
            pp.id
    ");

    $stmt->execute([
        ':prose_draft_id' => (int) $draft['id'],
    ]);

    $projections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            pas.id,
            pas.prose_entity_id,
            pas.subject_entity_id,
            pas.annotation_type_id,
            pas.annotation_value_id,
            pas.span_start,
            pas.span_end,
            pas.source_type_id,
            pas.created_at
        FROM sxnzlfun_chrysalis.prose_annotation_spans pas
        WHERE pas.prose_entity_id = :prose_entity_id
        ORDER BY
            pas.span_start IS NULL,
            pas.span_start,
            pas.span_end,
            pas.id
    ");

    $stmt->execute([
        ':prose_entity_id' => $proseEntityId,
    ]);

    $annotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $entityIds = [];
    foreach ($annotations as $annotation) {
        $entityIds[] = $annotation['annotation_type_id'];
        $entityIds[] = $annotation['annotation_value_id'];

        if (is_string($annotation['subject_entity_id']) && trim($annotation['subject_entity_id']) !== '') {
            $entityIds[] = $annotation['subject_entity_id'];
        }
    }

    $entitySummaries = prose_training_fetch_entity_summaries($pdo, $entityIds);

    $proseText = $draft['prose_body'];

    foreach ($annotations as &$annotation) {
        $typeId = $annotation['annotation_type_id'];
        $valueId = $annotation['annotation_value_id'];
        $subjectId = $annotation['subject_entity_id'];

        $annotation['type_entity'] = $entitySummaries[$typeId] ?? null;
        $annotation['value_entity'] = $entitySummaries[$valueId] ?? null;
        $annotation['subject_entity'] = is_string($subjectId)
            ? ($entitySummaries[$subjectId] ?? null)
            : null;

        if ($annotation['span_start'] !== null && $annotation['span_end'] !== null) {
            $annotation['span_text'] = mb_substr(
                $proseText,
                $annotation['span_start'],
                $annotation['span_end'] - $annotation['span_start']
            );
        } else {
            $annotation['span_text'] = null;
        }
    }
    unset($annotation);

    $characters = [];
    $voiceLabels = [];
    $limbicLabels = [];
    $expressionLabels = [];
    $themeLabels = [];

    foreach ($annotations as $annotation) {
        if ($annotation['subject_entity_id'] !== null) {
            $characters[$annotation['subject_entity_id']] = true;
        }

        $bucketKey = prose_training_annotation_bucket_key(
            $annotation['type_entity'] ?? []
        );

        if ($bucketKey === null) {
            continue;
        }

        $valueLabel = prose_training_annotation_value_label(
            $annotation['value_entity'] ?? null,
            $annotation['annotation_value_id']
        );

        if ($bucketKey === 'voice') {
            $voiceLabels[] = $valueLabel;
        } elseif ($bucketKey === 'limbic') {
            $limbicLabels[] = $valueLabel;
        } elseif ($bucketKey === 'expression') {
            $expressionLabels[] = $valueLabel;
        } elseif ($bucketKey === 'theme') {
            $themeLabels[] = $valueLabel;
        }
    }

    return [
        'status' => 'ok',

        'input' => [
            'prose_entity_id' => $draft['entity_id'],
            'title' => $draft['title'],
            'draft_status_id' => $draft['draft_status_id'],
            'author_entity_id' => $draft['author_entity_id'],
            'projected_contexts' => $projections,
            'subject_entity_ids' => array_keys($characters),
            'voice_labels' => array_values(array_unique($voiceLabels)),
            'limbic_labels' => array_values(array_unique($limbicLabels)),
            'expression_labels' => array_values(array_unique($expressionLabels)),
            'theme_labels' => array_values(array_unique($themeLabels)),
        ],

        'target' => [
            'prose' => $draft['prose_body'],
            'annotations' => $annotations,
        ],

        'training_policy' => [
            'prose_is_source_truth' => true,
            'annotations_are_curated_or_derived' => true,
            'predictions_are_not_persisted_as_truth' => true,
        ],
    ];
}
