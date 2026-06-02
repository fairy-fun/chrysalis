<?php

declare(strict_types=1);

function prose_reader_required_string(array $source, string $key): string
{
    $value = $source[$key] ?? null;

    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException($key . ' must be a non-empty string');
    }

    return trim($value);
}

function prose_draft_exists_for_reader(PDO $pdo, string $proseEntityId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.prose_drafts
        WHERE entity_id = :entity_id
    ");

    $stmt->execute([':entity_id' => $proseEntityId]);

    return (int) $stmt->fetchColumn() === 1;
}

function prose_fetch_entity_summaries(PDO $pdo, array $entityIds): array
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

function get_prose_annotations(PDO $pdo, string $proseEntityId): array
{
    if (!prose_draft_exists_for_reader($pdo, $proseEntityId)) {
        throw new RuntimeException('Prose not found: ' . $proseEntityId);
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            annotation_type_id,
            annotation_value_id,
            subject_entity_id,
            span_start,
            span_end,
            source_type_id,
            created_at
        FROM sxnzlfun_chrysalis.prose_annotation_spans
        WHERE prose_entity_id = :prose_entity_id
        ORDER BY
            span_start IS NULL,
            span_start ASC,
            span_end ASC,
            id ASC
    ");

    $stmt->execute([':prose_entity_id' => $proseEntityId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $entityIds = [];
    foreach ($rows as $row) {
        $entityIds[] = $row['annotation_type_id'];
        $entityIds[] = $row['annotation_value_id'];

        if (is_string($row['subject_entity_id']) && trim($row['subject_entity_id']) !== '') {
            $entityIds[] = $row['subject_entity_id'];
        }
    }

    $entitySummaries = prose_fetch_entity_summaries($pdo, $entityIds);

    return array_map(
        static function (array $row) use ($entitySummaries): array {
            $typeId = $row['annotation_type_id'];
            $valueId = $row['annotation_value_id'];
            $subjectId = $row['subject_entity_id'];

            return [
                'id' => (int) $row['id'],
                'type' => $typeId,
                'value' => $valueId,
                'subject' => $subjectId,
                'type_entity' => $entitySummaries[$typeId] ?? null,
                'value_entity' => $entitySummaries[$valueId] ?? null,
                'subject_entity' => is_string($subjectId)
                    ? ($entitySummaries[$subjectId] ?? null)
                    : null,
                'span' => $row['span_start'] === null
                    ? null
                    : [(int) $row['span_start'], (int) $row['span_end']],
                'source' => $row['source_type_id'],
                'created_at' => $row['created_at'],
            ];
        },
        $rows
    );
}

function get_prose_training_view(PDO $pdo, string $proseEntityId): array
{
    $stmt = $pdo->prepare("
        SELECT
            entity_id,
            title,
            prose_body,
            draft_status_id,
            author_entity_id,
            created_at,
            updated_at
        FROM sxnzlfun_chrysalis.prose_drafts
        WHERE entity_id = :entity_id
    ");

    $stmt->execute([':entity_id' => $proseEntityId]);

    $prose = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($prose)) {
        throw new RuntimeException('Prose not found: ' . $proseEntityId);
    }

    return [
        'prose' => [
            'entity_id' => $prose['entity_id'],
            'title' => $prose['title'],
            'body' => $prose['prose_body'],
            'draft_status_id' => $prose['draft_status_id'],
            'author_entity_id' => $prose['author_entity_id'],
            'created_at' => $prose['created_at'],
            'updated_at' => $prose['updated_at'],
        ],
        'annotations' => get_prose_annotations($pdo, $proseEntityId),
    ];
}
