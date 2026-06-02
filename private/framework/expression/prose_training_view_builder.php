<?php

const PROSE_TRAINING_ANNOTATION_TYPE_VOICE_ID = 'annotation_type_voice';
const PROSE_TRAINING_ANNOTATION_TYPE_LIMBIC_ID = 'annotation_type_limbic';
const PROSE_TRAINING_ANNOTATION_TYPE_EXPRESSION_ID = 'annotation_type_expression';
const PROSE_TRAINING_ANNOTATION_TYPE_THEME_ID = 'annotation_type_theme';

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

    $proseText = $draft['prose_body'];

    foreach ($annotations as &$annotation) {
        if ($annotation['span_start'] !== null && $annotation['span_end'] !== null) {
            $annotation['span_text'] = mb_substr(
                $proseText,
                $annotation['span_start'],
                $annotation['span_end'] - $annotation['span_start'] + 1
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
        $type = $annotation['annotation_type_id'];
        $value = $annotation['annotation_value_id'];

        if ($annotation['subject_entity_id'] !== null) {
            $characters[$annotation['subject_entity_id']] = true;
        }

        if ($type === PROSE_TRAINING_ANNOTATION_TYPE_VOICE_ID) {
            $voiceLabels[] = $value;
        } elseif ($type === PROSE_TRAINING_ANNOTATION_TYPE_LIMBIC_ID) {
            $limbicLabels[] = $value;
        } elseif ($type === PROSE_TRAINING_ANNOTATION_TYPE_EXPRESSION_ID) {
            $expressionLabels[] = $value;
        } elseif ($type === PROSE_TRAINING_ANNOTATION_TYPE_THEME_ID) {
            $themeLabels[] = $value;
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
