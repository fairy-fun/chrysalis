<?php

declare(strict_types=1);

require_once __DIR__ . '/entity_id_resolution.php';
require_once __DIR__ . '/entity_lookup.php';
require_once __DIR__ . '/request_context.php';

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

function build_create_label_sql(PDO $pdo, string $entityId, string $rawLabel): string
{
    $label = trim($rawLabel);

    return
        'INSERT INTO sxnzlfun_chrysalis.entity_texts ' .
        '(entity_id, canonical_label, summary, description, search_text, created_at, updated_at, nl_priority) ' .
        'SELECT ' .
        quote_sql_string($pdo, $entityId) . ', ' .
        quote_sql_string($pdo, $label) . ', ' .
        'NULL, NULL, ' .
        quote_sql_string($pdo, mb_strtolower($label, 'UTF-8')) . ', ' .
        'NOW(), NOW(), 0 ' .
        'FROM DUAL ' .
        'WHERE NOT EXISTS (' .
        'SELECT 1 ' .
        'FROM sxnzlfun_chrysalis.entity_texts et ' .
        'WHERE et.entity_id = ' . quote_sql_string($pdo, $entityId) .
        ');';
}

function suggest_link_entity_explicit_subject(
    PDO $pdo,
    string $subjectEntityId,
    string $rawLabel,
    string $entityTypeId,
    string $factTypeId
): array {
    $subjectEntityId = trim($subjectEntityId);
    $rawLabel = trim($rawLabel);
    $entityTypeId = trim($entityTypeId);
    $factTypeId = trim($factTypeId);

    if ($subjectEntityId === '') {
        throw new InvalidArgumentException('Subject entity id is required');
    }

    if ($rawLabel === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    if ($factTypeId === '') {
        throw new InvalidArgumentException('Fact type id is required');
    }

    if (!entity_exists_by_id($pdo, $subjectEntityId)) {
        throw new RuntimeException('Subject entity not found');
    }

    $existingEntityId = find_exact_entity_by_canonical_label($pdo, $rawLabel, $entityTypeId);

    if ($existingEntityId !== null) {
        return [
            'type' => 'sql_suggestion',
            'action' => 'link_entity_generic',
            'subject_entity_id' => $subjectEntityId,
            'entity_id' => $existingEntityId,
            'steps' => [
                [
                    'step' => 'link_entity',
                    'sql' => build_link_entity_sql(
                        $pdo,
                        $subjectEntityId,
                        $factTypeId,
                        $existingEntityId
                    ),
                ],
            ],
        ];
    }

    $resolution = resolve_or_suggest_entity_id($pdo, $rawLabel, $entityTypeId);
    $newEntityId = (string) $resolution['entity_id'];
    $resolutionCode = (string) $resolution['resolution_code'];

    return [
        'type' => 'sql_suggestion_bundle',
        'action' => 'create_and_link_entity_generic',
        'subject_entity_id' => $subjectEntityId,
        'entity_id_resolution' => [
            'entity_id' => $newEntityId,
            'mode' => $resolutionCode,
        ],
        'steps' => [
            [
                'step' => 'create_entity',
                'sql' => build_create_entity_sql($pdo, $newEntityId, $entityTypeId),
            ],
            [
                'step' => 'create_label',
                'sql' => build_create_label_sql($pdo, $newEntityId, $rawLabel),
            ],
            [
                'step' => 'link_entity',
                'sql' => build_link_entity_sql(
                    $pdo,
                    $subjectEntityId,
                    $factTypeId,
                    $newEntityId
                ),
            ],
        ],
    ];
}

function suggest_link_entity_from_request_context(
    PDO $pdo,
    string $rawLabel,
    string $entityTypeId,
    string $factTypeId
): array {
    $subjectEntityId = resolve_latest_subject_entity_id_from_request_context($pdo);

    return suggest_link_entity_explicit_subject(
        $pdo,
        $subjectEntityId,
        $rawLabel,
        $entityTypeId,
        $factTypeId
    );
}