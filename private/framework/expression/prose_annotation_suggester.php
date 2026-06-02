<?php

function prose_suggester_resolve_entity_backed_classval_ids(
    PDO $pdo,
    array $codes
): array {
    $codes = array_values(array_unique(array_filter(
        $codes,
        static fn ($value): bool => is_string($value) && trim($value) !== ''
    )));

    if ($codes === []) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($codes as $index => $code) {
        $placeholder = ':code_' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $code;
    }

    $stmt = $pdo->prepare(
        "
        SELECT
            c.code,
            e.id
        FROM sxnzlfun_chrysalis.entities e
        INNER JOIN sxnzlfun_chrysalis.classvals c
            ON c.id = e.id
        WHERE e.entity_type_id = 'entity_type_classval'
          AND c.code IN (" . implode(', ', $placeholders) . ")
        "
    );

    $stmt->execute($params);

    $resolved = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $resolved[$row['code']] = $row['id'];
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

    $patterns = [
        [
            'annotation_type_code' => 'annotation_type_expression',
            'annotation_value_code' => 'expression_contained',
            'needles' => ['contained', 'held still', 'kept still', 'swallowed', 'composed'],
        ],
        [
            'annotation_type_code' => 'annotation_type_limbic',
            'annotation_value_code' => 'limbic_stressed',
            'needles' => ['tightened', 'could not breathe', 'panic', 'shame', 'embarrassment'],
        ],
        [
            'annotation_type_code' => 'annotation_type_voice',
            'annotation_value_code' => 'voice_shay',
            'needles' => ['That’s nice', 'That’s cute', 'I’m obsessed', 'Yes, girl'],
        ],
    ];

    $requiredCodes = [];
    foreach ($patterns as $pattern) {
        $requiredCodes[] = $pattern['annotation_type_code'];
        $requiredCodes[] = $pattern['annotation_value_code'];
    }

    $resolvedIdsByCode = prose_suggester_resolve_entity_backed_classval_ids(
        $pdo,
        $requiredCodes
    );

    foreach ($requiredCodes as $requiredCode) {
        if (!isset($resolvedIdsByCode[$requiredCode])) {
            return [
                'status' => 'error',
                'message' => 'Missing entity-backed classval for prose annotation suggester code: ' . $requiredCode,
                'prose_entity_id' => $proseEntityId,
            ];
        }
    }

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
                'annotation_type_id' => $resolvedIdsByCode[$pattern['annotation_type_code']],
                'annotation_value_id' => $resolvedIdsByCode[$pattern['annotation_value_code']],
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
