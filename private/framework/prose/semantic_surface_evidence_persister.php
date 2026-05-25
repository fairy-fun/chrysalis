<?php

declare(strict_types=1);

function semantic_surface_table_columns(PDO $pdo, string $tableName): array
{
    static $cache = [];

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    try {
        $stmt = $pdo->query('DESCRIBE ' . $tableName);
    } catch (Throwable $e) {
        $cache[$tableName] = [];
        return [];
    }

    if (!$stmt instanceof PDOStatement) {
        $cache[$tableName] = [];
        return [];
    }

    $columns = [];

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        if (!is_array($row)) {
            continue;
        }

        $field = trim((string)($row['Field'] ?? ''));

        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    $cache[$tableName] = $columns;

    return $columns;
}

function semantic_surface_build_insertable_row(
    array $columns,
    array $candidateRow
): array {

    $row = [];

    foreach ($candidateRow as $field => $value) {
        if (isset($columns[$field])) {
            $row[$field] = $value;
        }
    }

    return $row;
}

function semantic_surface_required_event_id(array $context): int
{
    $eventId = $context['event_id'] ?? null;

    if ($eventId === null || (int)$eventId < 1) {
        throw new RuntimeException(
            'semantic_surface_evidence requires event_id; missing provenance context for workflow=' .
            (string)($context['workflow'] ?? 'unknown') .
            ', calendar_event_entity_id=' .
            (string)($context['calendar_event_entity_id'] ?? 'unknown') .
            ', prose_projection_id=' .
            (string)($context['prose_projection_id'] ?? 'unknown') .
            ', prose_entity_id=' .
            (string)($context['prose_entity_id'] ?? 'unknown')
        );
    }

    return (int)$eventId;
}

function persist_semantic_surface_evidence(
    PDO $pdo,
    string $surfaceText,
    array $context = [],
    ?array $selectedCandidate = null
): ?int {

    $surfaceText = trim($surfaceText);

    if ($surfaceText === '') {
        return null;
    }

    $columns = semantic_surface_table_columns(
        $pdo,
        'semantic_surface_evidence'
    );

    if ($columns === []) {
        return null;
    }

    $eventId = semantic_surface_required_event_id($context);
    $createdAt = date('Y-m-d H:i:s');
    $advisoryEvidenceStatus = 'EVIDENCE_STATUS_ADVISORY';
    $matchedText = $context['matched_text'] ?? $surfaceText;
    $normalizedSurfaceText = $context['normalized_surface_text'] ?? null;
    $resolutionStrategy = $context['resolution_strategy'] ?? null;

    $extractionNotes = json_encode(
        [
            'resolution_strategy' => $resolutionStrategy,
            'matched_text' => $matchedText,
            'normalized_surface_text' => $normalizedSurfaceText,
            'selected_resolution_method_classval_id'
                => $selectedCandidate['resolution_method_classval_id'] ?? null,
            'selected_candidate_score'
                => $selectedCandidate['candidate_score'] ?? null,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    $candidateRow = [
        'surface_text' => $surfaceText,
        'event_id' => $eventId,
        'calendar_event_entity_id' => $context['calendar_event_entity_id'] ?? null,
        'prose_projection_id' => $context['prose_projection_id'] ?? null,
        'prose_entity_id' => $context['prose_entity_id'] ?? null,
        'source_offset_start' => $context['source_offset_start'] ?? $context['span_start'] ?? null,
        'source_offset_end' => $context['source_offset_end'] ?? $context['span_end'] ?? null,
        'normalized_surface_text' => $normalizedSurfaceText,
        'extraction_notes' => $extractionNotes,
        'source_classval_id' => $context['source_classval_id'] ?? 'SURFACE_SOURCE_SEMANTIC_ALIAS',
        'confidence_classval_id' => $context['confidence_classval_id'] ?? 'CONFIDENCE_MEDIUM',
        'resolution_status_classval_id' => $context['resolution_status_classval_id'] ?? $advisoryEvidenceStatus,
        'evidence_status_classval_id' => $context['evidence_status_classval_id'] ?? $advisoryEvidenceStatus,
        'resolved_entity_id' => $selectedCandidate['resolved_entity_id'] ?? null,
        'resolution_method_classval_id' => $selectedCandidate['resolution_method_classval_id'] ?? null,
        'scoring_notes' => $selectedCandidate['scoring_notes'] ?? null,
        'created_at' => $createdAt,
    ];

    if (isset($columns['source_context_json'])) {
        $candidateRow['source_context_json'] = json_encode(
            [
                'workflow' => $context['workflow'] ?? null,
                'suggestion_mode' => 'deterministic_ordered_resolution_candidates',
                'matched_surface_form' => $surfaceText,
                'matched_text' => $matchedText,
                'normalized_surface_text' => $normalizedSurfaceText,
                'resolution_strategy' => $resolutionStrategy,
                'event_id' => $eventId,
                'calendar_event_entity_id' => $context['calendar_event_entity_id'] ?? null,
                'prose_projection_id' => $context['prose_projection_id'] ?? null,
                'prose_entity_id' => $context['prose_entity_id'] ?? null,
                'source_offset_start' => $candidateRow['source_offset_start'],
                'source_offset_end' => $candidateRow['source_offset_end'],
                'created_at' => $createdAt,
                'advisory_evidence_status' => $advisoryEvidenceStatus,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    $row = semantic_surface_build_insertable_row(
        $columns,
        $candidateRow
    );

    if (!isset($row['surface_text'])) {
        return null;
    }

    if (!isset($row['event_id']) || (int)$row['event_id'] < 1) {
        throw new RuntimeException(
            'semantic_surface_evidence requires event_id; insertable provenance row is invalid'
        );
    }

    if (isset($columns['created_at']) && !isset($row['created_at'])) {
        throw new RuntimeException(
            'semantic_surface_evidence requires created_at; insertable provenance row is invalid'
        );
    }

    $fieldNames = array_keys($row);
    $placeholders = array_map(
        static fn (string $field): string => ':' . $field,
        $fieldNames
    );

    $sql = 'INSERT INTO semantic_surface_evidence (' .
        implode(', ', $fieldNames) .
        ') VALUES (' .
        implode(', ', $placeholders) .
        ')';

    $stmt = $pdo->prepare($sql);

    $params = [];

    foreach ($row as $field => $value) {
        $params[':' . $field] = $value;
    }

    $stmt->execute($params);

    $id = (int)$pdo->lastInsertId();

    return $id > 0 ? $id : null;
}
