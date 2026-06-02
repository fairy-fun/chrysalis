<?php

function prose_suggester_resolve_entity_backed_classval_ids_by_label(
    PDO $pdo,
    array $specs
): array {
    if ($specs === []) {
        return [];
    }

    $resolved = [];

    foreach ($specs as $key => $spec) {
        $label = $spec['label'] ?? null;

        if (!is_string($label) || trim($label) === '') {
            throw new InvalidArgumentException(
                'Missing prose annotation suggester label for key: ' . $key
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                e.id
            FROM sxnzlfun_chrysalis.entities e
            INNER JOIN sxnzlfun_chrysalis.classvals c
                ON c.id = e.id
            LEFT JOIN sxnzlfun_chrysalis.entity_texts et
                ON et.entity_id = e.id
            WHERE e.entity_type_id = 'entity_type_classval'
              AND (
                    c.label = :label
                 OR et.canonical_label = :label
              )
            GROUP BY e.id
            ORDER BY e.id ASC
            "
        );

        $stmt->execute([
            ':label' => trim($label),
        ]);

        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($matches) !== 1) {
            throw new RuntimeException(
                'Expected exactly one entity-backed classval for prose annotation suggester label "'
                . $label
                . '", found '
                . count($matches)
            );
        }

        $resolved[$key] = (string) $matches[0];
    }

    return $resolved;
}

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

    $annotationEntitySpecs = [
        'expression_type' => ['label' => 'Expression'],
        'expression_value' => ['label' => 'Contained'],
        'limbic_type' => ['label' => 'Limbic'],
        'limbic_value' => ['label' => 'Stressed'],
        'voice_type' => ['label' => 'Voice'],
        'voice_value' => ['label' => 'Shay'],
    ];

    try {
        $resolvedIds = prose_suggester_resolve_entity_backed_classval_ids_by_label(
            $pdo,
            $annotationEntitySpecs
        );
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'prose_entity_id' => $proseEntityId,
        ];
    }

    $patterns = [
        [
            'annotation_type_id' => $resolvedIds['expression_type'],
            'annotation_value_id' => $resolvedIds['expression_value'],
            'needles' => ['contained', 'held still', 'kept still', 'swallowed', 'composed'],
        ],
        [
            'annotation_type_id' => $resolvedIds['limbic_type'],
            'annotation_value_id' => $resolvedIds['limbic_value'],
            'needles' => ['tightened', 'could not breathe', 'panic', 'shame', 'embarrassment'],
        ],
        [
            'annotation_type_id' => $resolvedIds['voice_type'],
            'annotation_value_id' => $resolvedIds['voice_value'],
            'needles' => ['That’s nice', 'That’s cute', 'I’m obsessed', 'Yes, girl'],
        ],
    ];

    foreach ($patterns as $pattern) {
        foreach ($pattern['needles'] as $needle) {
            $offset = mb_stripos($body, $needle);

            if ($offset === false) {
                continue;
            }

            $spanEnd = $offset + mb_strlen($needle);

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

    if (($suggestions['status'] ?? null) !== 'ok') {
        return $suggestions;
    }

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
