<?php

function suggestProseAnnotations(
    PDO $pdo,
    string $proseEntityId,
    ?string $subjectEntityId = null
): array {
    $stmt = $pdo->prepare("
        SELECT
            pd.id,
            pd.entity_id,
            pd.title,
            pd.prose_body,
            pd.draft_status_id
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

    $suggestions = [];

    /*
     * Baseline heuristic layer.
     * This is suggestion-only. Do not persist these as curated truth.
     * Later, this can be replaced or enriched by expression_constraint_outputs.
     */

    $body = $draft['prose_body'];

    $patterns = [
        [
            'annotation_type_id' => 'annotation_type_expression',
            'annotation_value_id' => 'expression_contained',
            'needles' => ['contained', 'held still', 'kept still', 'swallowed', 'composed'],
        ],
        [
            'annotation_type_id' => 'annotation_type_limbic',
            'annotation_value_id' => 'limbic_stressed',
            'needles' => ['tightened', 'could not breathe', 'panic', 'shame', 'embarrassment'],
        ],
        [
            'annotation_type_id' => 'annotation_type_voice',
            'annotation_value_id' => 'voice_shay',
            'needles' => ['That’s nice', 'That’s cute', 'I’m obsessed', 'Yes, girl'],
        ],
    ];

    foreach ($patterns as $pattern) {
        foreach ($pattern['needles'] as $needle) {
            $offset = mb_stripos($body, $needle);

            if ($offset === false) {
                continue;
            }

            $spanEnd = $offset + mb_strlen($needle) - 1;

            $suggestions[] = [
                'prose_entity_id' => $proseEntityId,
                'subject_entity_id' => $subjectEntityId,
                'annotation_type_id' => $pattern['annotation_type_id'],
                'annotation_value_id' => $pattern['annotation_value_id'],
                'span_start' => $offset,
                'span_end' => $spanEnd,
                'span_text' => mb_substr($body, $offset, mb_strlen($needle)),
                'source_type_id' => 'annotation_source_suggestion',
                'proposal_type' => 'prose_annotation_suggestion',
                'persist' => false,
            ];
        }
    }

    return [
        'status' => 'ok',
        'prose_entity_id' => $proseEntityId,
        'subject_entity_id' => $subjectEntityId,
        'suggestion_count' => count($suggestions),
        'suggestions' => $suggestions,
    ];
}

function reviewProseAnnotationSuggestions(
    PDO $pdo,
    string $proseEntityId,
    ?string $subjectEntityId = null
): array {
    $suggestions = suggestProseAnnotations($pdo, $proseEntityId, $subjectEntityId);

    $stmt = $pdo->prepare("
        SELECT
            annotation_type_id,
            annotation_value_id,
            span_start,
            span_end
        FROM sxnzlfun_chrysalis.prose_annotation_spans
        WHERE prose_entity_id = :prose_entity_id
    ");

    $stmt->execute([
        ':prose_entity_id' => $proseEntityId,
    ]);

    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $existingSet = [];
    foreach ($existing as $row) {
        $key = implode('|', [
            $row['annotation_type_id'],
            $row['annotation_value_id'],
            $row['span_start'] ?? 'null',
            $row['span_end'] ?? 'null',
        ]);
        $existingSet[$key] = true;
    }

    foreach ($suggestions['suggestions'] as &$s) {
        $key = implode('|', [
            $s['annotation_type_id'],
            $s['annotation_value_id'],
            $s['span_start'] ?? 'null',
            $s['span_end'] ?? 'null',
        ]);

        $s['already_applied'] = isset($existingSet[$key]);
    }
    unset($s);

    return $suggestions;
}