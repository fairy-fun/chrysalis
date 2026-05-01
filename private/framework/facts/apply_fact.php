<?php

declare(strict_types=1);

function apply_event_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $contextEntityId,
    string $factTypeId,
    string $objectEntityId,
    ?string $sourceDocument = null,
    ?string $notes = null
): array {
    if ($subjectEntityId === '' || $contextEntityId === '' || $factTypeId === '' || $objectEntityId === '') {
        throw new InvalidArgumentException('All core fields are required for event fact');
    }

    $stmt = $pdo->prepare(<<<SQL
INSERT INTO entity_linked_facts_event (
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    object_entity_id,
    source_document,
    notes
)
VALUES (
    :subject,
    :context,
    :fact_type,
    :object,
    :source,
    :notes
)
ON DUPLICATE KEY UPDATE
    linked_fact_id = linked_fact_id
SQL);

    $stmt->execute([
        'subject' => $subjectEntityId,
        'context' => $contextEntityId,
        'fact_type' => $factTypeId,
        'object' => $objectEntityId,
        'source' => $sourceDocument,
        'notes' => $notes,
    ]);

    return [
        'status' => $stmt->rowCount() > 0 ? 'applied' : 'duplicate',
        'table' => 'entity_linked_facts_event',
        'fact' => [
            'subject_entity_id' => $subjectEntityId,
            'context_entity_id' => $contextEntityId,
            'fact_type_id' => $factTypeId,
            'object_entity_id' => $objectEntityId,
        ],
    ];
}

function apply_global_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $factTypeId,
    string $objectEntityId,
    ?string $sourceDocument = null,
    ?string $notes = null
): array {
    if ($subjectEntityId === '' || $factTypeId === '' || $objectEntityId === '') {
        throw new InvalidArgumentException('All core fields are required for global fact');
    }

    $stmt = $pdo->prepare(<<<SQL
INSERT INTO entity_linked_facts_global (
    subject_entity_id,
    fact_type_id,
    object_entity_id,
    source_document,
    notes
)
VALUES (
    :subject,
    :fact_type,
    :object,
    :source,
    :notes
)
ON DUPLICATE KEY UPDATE
    linked_fact_id = linked_fact_id
SQL);

    $stmt->execute([
        'subject' => $subjectEntityId,
        'fact_type' => $factTypeId,
        'object' => $objectEntityId,
        'source' => $sourceDocument,
        'notes' => $notes,
    ]);

    return [
        'status' => $stmt->rowCount() > 0 ? 'applied' : 'duplicate',
        'table' => 'entity_linked_facts_global',
        'fact' => [
            'subject_entity_id' => $subjectEntityId,
            'fact_type_id' => $factTypeId,
            'object_entity_id' => $objectEntityId,
        ],
    ];
}

function assert_not_legacy_fact_table(string $sql): void
{
    if (stripos($sql, 'entity_linked_facts ') !== false &&
        stripos($sql, 'entity_linked_facts_event') === false &&
        stripos($sql, 'entity_linked_facts_global') === false
    ) {
        throw new RuntimeException('Write attempted against legacy entity_linked_facts view');
    }
}