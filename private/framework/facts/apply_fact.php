<?php

declare(strict_types=1);

require_once __DIR__ . '/fact_governance.php';

function assert_not_legacy_fact_table(string $sql): void
{
    $pattern = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:`?[\w]+`?\.)?`?entity_linked_facts`?\b/i';

    if (preg_match($pattern, $sql) === 1) {
        throw new RuntimeException(
            'Write attempted against entity_linked_facts compatibility view. ' .
            'Use entity_linked_facts_event or entity_linked_facts_global.'
        );
    }
}

function prepare_fact_write(PDO $pdo, string $sql): PDOStatement
{
    assert_not_legacy_fact_table($sql);
    return $pdo->prepare($sql);
}

function apply_event_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $contextEntityId,
    string $factTypeId,
    string $objectEntityId,
    ?string $sourceDocument = null,
    ?string $notes = null,
    ?array $governance = null
): array {
    if (
        $subjectEntityId === '' ||
        $contextEntityId === '' ||
        $factTypeId === '' ||
        $objectEntityId === ''
    ) {
        throw new InvalidArgumentException('All core fields are required for event fact');
    }

    $governance = resolve_fact_governance($governance);

    $stmt = prepare_fact_write($pdo, <<<SQL
INSERT INTO entity_linked_facts_event (
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    object_entity_id,
    source_document,
    notes,
    epistemic_origin_classval_id,
    adjudication_status_classval_id,
    contradiction_state_classval_id
)
VALUES (
    :subject,
    :context,
    :fact_type,
    :object,
    :source,
    :notes,
    :epistemic_origin,
    :adjudication_status,
    :contradiction_state
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
        'epistemic_origin' => $governance['epistemic_origin_classval_id'],
        'adjudication_status' => $governance['adjudication_status_classval_id'],
        'contradiction_state' => $governance['contradiction_state_classval_id'],
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
    ?string $notes = null,
    ?array $governance = null
): array {
    if (
        $subjectEntityId === '' ||
        $factTypeId === '' ||
        $objectEntityId === ''
    ) {
        throw new InvalidArgumentException('All core fields are required for global fact');
    }

    $governance = resolve_fact_governance($governance);

    $stmt = prepare_fact_write($pdo, <<<SQL
INSERT INTO entity_linked_facts_global (
    subject_entity_id,
    fact_type_id,
    object_entity_id,
    source_document,
    notes,
    epistemic_origin_classval_id,
    adjudication_status_classval_id,
    contradiction_state_classval_id
)
VALUES (
    :subject,
    :fact_type,
    :object,
    :source,
    :notes,
    :epistemic_origin,
    :adjudication_status,
    :contradiction_state
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
        'epistemic_origin' => $governance['epistemic_origin_classval_id'],
        'adjudication_status' => $governance['adjudication_status_classval_id'],
        'contradiction_state' => $governance['contradiction_state_classval_id'],
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