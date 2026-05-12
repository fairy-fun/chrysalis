<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);

require_once $repoRoot . '/private/framework/api/api_bootstrap.php';
require_once $repoRoot . '/private/framework/facts/apply_fact.php';
require_once $repoRoot . '/private/framework/facts/fact_governance.php';
require_once $repoRoot . '/private/framework/facts/fact_resolver.php';

function validate_fact_lineage_conflicts(): void
{
    $pdoA = makePdo();
    $pdoB = makePdo();

    $cleanupPdo = makePdo();
    $cleanupPdo->beginTransaction();

    $subjectEntityId =
        'ci_lineage_conflict_subject_' . bin2hex(random_bytes(8));

    $factTypeId = 'ci_lineage_conflict_status';

    $initialObjectEntityId =
        'ci_lineage_conflict_status_initial';

    $branchAObjectEntityId = $initialObjectEntityId;
    $branchBObjectEntityId = $initialObjectEntityId;

    $initial = apply_global_fact(
        $pdoA,
        $subjectEntityId,
        $factTypeId,
        $initialObjectEntityId,
        'ci_lineage_conflicts',
        'Initial lineage head',
        [
            'adjudication_status_classval_id'
            => governance_default_adjudication_status($pdoA),
        ]
    );

    $initialHeadId = (int) $initial['linked_fact_id'];

    if ($initialHeadId < 1) {
        throw new RuntimeException(
            'Initial lineage head creation failed'
        );
    }

    /*
     * Begin competing lineage transactions.
     */

    $pdoA->beginTransaction();
    $pdoB->beginTransaction();

    $branchASucceeded = false;
    $branchBConflictObserved = false;

    try {

        apply_global_fact(
            $pdoA,
            $subjectEntityId,
            $factTypeId,
            $branchAObjectEntityId,
            'ci_lineage_conflicts',
            'Branch A advancement',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdoA),
            ],
            $initialHeadId
        );

        $branchASucceeded = true;

        /*
         * Commit A first so UNIQUE(supersedes_linked_fact_id)
         * becomes globally visible.
         */

        $pdoA->commit();

        /*
         * Re-enter rollbackable cleanup transaction boundary.
         */
        $cleanupPdo->exec('SET autocommit = 0');

        try {

            $governance = resolve_fact_governance($pdoB, [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdoB),
            ]);

            $stmt = prepare_fact_write($pdoB, "
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
    ");

            $stmt->execute([
                'subject' => $subjectEntityId,
                'fact_type' => $factTypeId,
                'object' => $branchBObjectEntityId,
                'source' => 'ci_lineage_conflicts',
                'notes' => 'Branch B fork attempt',
                'epistemic_origin'
                => $governance['epistemic_origin_classval_id'],
                'adjudication_status'
                => $governance['adjudication_status_classval_id'],
                'contradiction_state'
                => $governance['contradiction_state_classval_id'],
                'supersedes_linked_fact_id' => $initialHeadId,
            ]);

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                $branchBConflictObserved = true;
            } else {
                throw $e;
            }
        }

        if (!$branchASucceeded) {
            throw new RuntimeException(
                'Primary lineage advancement did not succeed'
            );
        }

        if (!$branchBConflictObserved) {
            throw new RuntimeException(
                'Fork attempt did not trigger lineage conflict detection'
            );
        }

        $canonical = resolve_canonical_global_fact(
            $pdoA,
            $subjectEntityId,
            $factTypeId,
            null,
            false
        );

        if ($canonical === null) {
            throw new RuntimeException(
                'Canonical lineage head disappeared after conflict test'
            );
        }

        if (
            ($canonical['object_entity_id'] ?? null)
            !==
            $branchAObjectEntityId
        ) {
            throw new RuntimeException(
                'Unexpected canonical lineage head selected'
            );
        }

        if (
            (int) ($canonical['supersedes_linked_fact_id'] ?? 0)
            !==
            $initialHeadId
        ) {
            throw new RuntimeException(
                'Canonical lineage head does not reference expected parent'
            );
        }

        $forkStmt = $pdoA->prepare("
            SELECT COUNT(*)
            FROM entity_linked_facts_global
            WHERE subject_entity_id = :subject
              AND fact_type_id = :fact_type
              AND object_entity_id = :object
              AND notes = 'Branch B fork attempt'
        ");

        $forkStmt->execute([
            'subject' => $subjectEntityId,
            'fact_type' => $factTypeId,
            'object' => $initialObjectEntityId,
        ]);

        if ((int) $forkStmt->fetchColumn() !== 0) {
            throw new RuntimeException(
                'Fork lineage branch unexpectedly persisted'
            );
        }

    } catch (Throwable $e) {

        if ($pdoA->inTransaction()) {
            $pdoA->rollBack();
        }

        if ($pdoB->inTransaction()) {
            $pdoB->rollBack();
        }

        throw $e;
    }

    if ($pdoB->inTransaction()) {
        $pdoB->rollBack();
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {

    try {

        validate_fact_lineage_conflicts();

        fwrite(
            STDOUT,
            "OK: Fact lineage conflict validation passed\n"
        );

    } catch (Throwable $e) {

        fwrite(
            STDERR,
            'FAIL: ' . $e->getMessage() . PHP_EOL
        );

        if ($e->getPrevious() !== null) {
            fwrite(
                STDERR,
                'Previous: '
                . $e->getPrevious()->getMessage()
                . PHP_EOL
            );
        }

        exit(1);
    }
}