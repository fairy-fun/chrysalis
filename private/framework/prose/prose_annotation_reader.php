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

    return array_map(
        static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'type' => $row['annotation_type_id'],
                'value' => $row['annotation_value_id'],
                'subject' => $row['subject_entity_id'],
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