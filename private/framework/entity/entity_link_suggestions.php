<?php

declare(strict_types=1);

function quote_sql_string(PDO $pdo, string $value): string
{
    $quoted = $pdo->quote($value);

    if (!is_string($quoted)) {
        throw new RuntimeException('Unable to quote SQL string');
    }

    return $quoted;
}

function build_link_entity_sql(
    PDO $pdo,
    string $subjectEntityId,
    string $factTypeId,
    string $objectEntityId
): string {
    return
        'INSERT INTO sxnzlfun_chrysalis.entity_linked_facts ' .
        '(subject_entity_id, fact_type_id, object_entity_id, notes, source_document, created_at, updated_at) ' .
        'SELECT ' .
        quote_sql_string($pdo, $subjectEntityId) . ', ' .
        quote_sql_string($pdo, $factTypeId) . ', ' .
        quote_sql_string($pdo, $objectEntityId) . ', ' .
        'NULL, NULL, NOW(), NOW() ' .
        'FROM DUAL ' .
        'WHERE NOT EXISTS (' .
        'SELECT 1 ' .
        'FROM sxnzlfun_chrysalis.entity_linked_facts elf ' .
        'WHERE elf.subject_entity_id = ' . quote_sql_string($pdo, $subjectEntityId) . ' ' .
        'AND elf.fact_type_id = ' . quote_sql_string($pdo, $factTypeId) . ' ' .
        'AND elf.object_entity_id = ' . quote_sql_string($pdo, $objectEntityId) .
        ');';
}

function build_create_entity_sql(PDO $pdo, string $entityId, string $entityTypeId): string
{
    return
        'INSERT INTO sxnzlfun_chrysalis.entities ' .
        '(id, entity_type_id) ' .
        'SELECT ' .
        quote_sql_string($pdo, $entityId) . ', ' .
        quote_sql_string($pdo, $entityTypeId) . ' ' .
        'FROM DUAL ' .
        'WHERE NOT EXISTS (' .
        'SELECT 1 ' .
        'FROM sxnzlfun_chrysalis.entities e ' .
        'WHERE e.id = ' . quote_sql_string($pdo, $entityId) .
        ');';
}

function build_create_label_sql(
    PDO $pdo,
    string $entityId,
    string $entityTypeId,
    string $rawLabel
): string {
    $entityId = trim($entityId);
    $entityTypeId = trim($entityTypeId);
    $label = trim($rawLabel);

    if ($entityId === '') {
        throw new RuntimeException('entity_id must not be empty when creating entity_texts row');
    }

    if ($entityTypeId === '') {
        throw new RuntimeException('entity_type_id must not be empty when creating entity_texts row');
    }

    if ($label === '') {
        throw new RuntimeException('canonical_label must not be empty when creating entity_texts row');
    }

    return
        'INSERT INTO sxnzlfun_chrysalis.entity_texts ' .
        '(entity_id, entity_type_id, canonical_label, summary, description, search_text, created_at, updated_at, nl_priority) ' .
        'SELECT ' .
        quote_sql_string($pdo, $entityId) . ', ' .
        quote_sql_string($pdo, $entityTypeId) . ', ' .
        quote_sql_string($pdo, $label) . ', ' .
        'NULL, NULL, ' .
        quote_sql_string($pdo, mb_strtolower($label, 'UTF-8')) . ', ' .
        'NOW(), NOW(), 0 ' .
        'FROM DUAL ' .
        'WHERE NOT EXISTS (' .
        'SELECT 1 ' .
        'FROM sxnzlfun_chrysalis.entity_texts et ' .
        'WHERE et.entity_id = ' . quote_sql_string($pdo, $entityId) . ' ' .
        'AND et.entity_type_id = ' . quote_sql_string($pdo, $entityTypeId) . ' ' .
        'AND et.canonical_label = ' . quote_sql_string($pdo, $label) .
        ');';
}