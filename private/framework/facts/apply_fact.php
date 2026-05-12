<?php

declare(strict_types=1);

require_once __DIR__ . '/fact_governance.php';
require_once __DIR__ . '/fact_lineage.php';
require_once __DIR__ . '/fact_resolver.php';

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

function is_fact_lineage_conflict_exception(PDOException $e): bool
{
    return $e->getCode() === '23000';
}

function transactional_fact_write(
    PDO $pdo,
    callable $callback
): array {
    $startedTransaction = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $result = $callback();

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $result;

    } catch (PDOException $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (is_fact_lineage_conflict_exception($e)) {
            throw new RuntimeException(
                'Fact lineage conflict detected',
                0,
                $e
            );
        }

        throw $e;

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function apply_event_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $contextEntityId,
    string $factTypeId,
    string $objectEntityId,
    ?string $sourceDocument = null,
    ?string $notes = null,
    ?array $governance = null,
    ?int $supersedesLinkedFactId = null
): array {
    if (
        $subjectEntityId === '' ||
        $contextEntityId === '' ||
        $factTypeId === '' ||
        $objectEntityId === ''
    ) {
        throw new InvalidArgumentException(
            'All core fields are required for event fact'
        );
    }

    $write = function () use (
        $pdo,
        $subjectEntityId,
        $contextEntityId,
        $factTypeId,
        $objectEntityId,
        $sourceDocument,
        $notes,
        $governance,
        $supersedesLinkedFactId
    ): array {
        $current = resolve_canonical_event_fact(
            $pdo,
            $subjectEntityId,
            $contextEntityId,
            $factTypeId,
            $objectEntityId,
            false,
            true
        );

        $resolvedSupersedesId = null;

        if ($current !== null) {
            $currentHeadId = (int) $current['linked_fact_id'];

            if ($supersedesLinkedFactId === null) {
                throw new RuntimeException(
                    'Explicit supersedesLinkedFactId is required when advancing an existing event fact lineage'
                );
            }

            if ($supersedesLinkedFactId !== $currentHeadId) {
                throw new RuntimeException(
                    'Supersession target is not current event fact lineage head'
                );
            }

            $resolvedSupersedesId = $currentHeadId;

            assert_valid_fact_supersession(
                $pdo,
                $resolvedSupersedesId,
                [
                    'subject_entity_id' => $subjectEntityId,
                    'context_entity_id' => $contextEntityId,
                    'fact_type_id' => $factTypeId,
                    'object_entity_id' => $objectEntityId,
                ],
                true
            );
        } elseif ($supersedesLinkedFactId !== null) {
            throw new RuntimeException(
                'Cannot supersede event fact because no current lineage head exists for this slot'
            );
        }

        $governanceResolved = resolve_fact_governance($pdo,$governance);

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
    contradiction_state_classval_id,
    supersedes_linked_fact_id
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
    :contradiction_state,
    :supersedes_linked_fact_id
)
SQL);

        $stmt->execute([
            'subject' => $subjectEntityId,
            'context' => $contextEntityId,
            'fact_type' => $factTypeId,
            'object' => $objectEntityId,
            'source' => $sourceDocument,
            'notes' => $notes,
            'epistemic_origin' => $governanceResolved['epistemic_origin_classval_id'],
            'adjudication_status' => $governanceResolved['adjudication_status_classval_id'],
            'contradiction_state' => $governanceResolved['contradiction_state_classval_id'],
            'supersedes_linked_fact_id' => $resolvedSupersedesId,
        ]);

        $linkedFactId = (int) $pdo->lastInsertId();

        return [
            'status' => 'applied',
            'created' => true,
            'linked_fact_id' => $linkedFactId,
            'table' => 'entity_linked_facts_event',
            'fact' => [
                'linked_fact_id' => $linkedFactId,
                'subject_entity_id' => $subjectEntityId,
                'context_entity_id' => $contextEntityId,
                'fact_type_id' => $factTypeId,
                'object_entity_id' => $objectEntityId,
                'supersedes_linked_fact_id' => $resolvedSupersedesId,
            ],
        ];
    };

    return transactional_fact_write($pdo, $write);
}

// private/framework/facts/apply_fact.php

function apply_global_fact(
    PDO $pdo,
    string $subjectEntityId,
    string $factTypeId,
    string $objectEntityId,
    ?string $sourceDocument = null,
    ?string $notes = null,
    ?array $governance = null,
    ?int $supersedesLinkedFactId = null
): array {
    if (
        $subjectEntityId === '' ||
        $factTypeId === '' ||
        $objectEntityId === ''
    ) {
        throw new InvalidArgumentException(
            'All core fields are required for global fact'
        );
    }

    $write = function () use (
        $pdo,
        $subjectEntityId,
        $factTypeId,
        $objectEntityId,
        $sourceDocument,
        $notes,
        $governance,
        $supersedesLinkedFactId
    ): array {
        $current = resolve_canonical_global_fact(
            $pdo,
            $subjectEntityId,
            $factTypeId,
            $objectEntityId,
            false,
            true
        );

        $resolvedSupersedesId = null;

        if ($current !== null) {
            $currentHeadId = (int) $current['linked_fact_id'];

            if ($supersedesLinkedFactId === null) {
                throw new RuntimeException(
                    'Explicit supersedesLinkedFactId is required when advancing an existing global fact lineage'
                );
            }

            if ($supersedesLinkedFactId !== $currentHeadId) {
                throw new RuntimeException(
                    'Supersession target is not a lineage head for this global fact slot'
                );
            }

            $resolvedSupersedesId = $currentHeadId;

            assert_valid_fact_supersession(
                $pdo,
                $resolvedSupersedesId,
                [
                    'subject_entity_id' => $subjectEntityId,
                    'fact_type_id' => $factTypeId,
                    'object_entity_id' => $objectEntityId,
                ],
                false
            );

        } elseif ($supersedesLinkedFactId !== null) {
            throw new RuntimeException(
                'Cannot supersede global fact because no current lineage head exists for this slot'
            );
        }

        $governanceResolved = resolve_fact_governance($pdo,$governance);

        $stmt = prepare_fact_write($pdo, <<<SQL
INSERT INTO entity_linked_facts_global (
    subject_entity_id,
    fact_type_id,
    object_entity_id,
    source_document,
    notes,
    epistemic_origin_classval_id,
    adjudication_status_classval_id,
    contradiction_state_classval_id,
    supersedes_linked_fact_id
)
VALUES (
    :subject,
    :fact_type,
    :object,
    :source,
    :notes,
    :epistemic_origin,
    :adjudication_status,
    :contradiction_state,
    :supersedes_linked_fact_id
)
SQL);

        $stmt->execute([
            'subject' => $subjectEntityId,
            'fact_type' => $factTypeId,
            'object' => $objectEntityId,
            'source' => $sourceDocument,
            'notes' => $notes,
            'epistemic_origin' => $governanceResolved['epistemic_origin_classval_id'],
            'adjudication_status' => $governanceResolved['adjudication_status_classval_id'],
            'contradiction_state' => $governanceResolved['contradiction_state_classval_id'],
            'supersedes_linked_fact_id' => $resolvedSupersedesId,
        ]);

        $linkedFactId = (int) $pdo->lastInsertId();

        return [
            'status' => 'applied',
            'created' => true,
            'linked_fact_id' => $linkedFactId,
            'table' => 'entity_linked_facts_global',
            'fact' => [
                'linked_fact_id' => $linkedFactId,
                'subject_entity_id' => $subjectEntityId,
                'fact_type_id' => $factTypeId,
                'object_entity_id' => $objectEntityId,
                'supersedes_linked_fact_id' => $resolvedSupersedesId,
            ],
        ];
    };

    return transactional_fact_write($pdo, $write);
}