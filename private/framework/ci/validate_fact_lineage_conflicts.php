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

    $subjectEntityId =
        'ci_lineage_conflict_subject_' . bin2hex(random_bytes(8));

    $factTypeId = 'ci_lineage_conflict_status';

    $initialObjectEntityId =
        'ci_lineage_conflict_status_initial';

    $branchAObjectEntityId =
        'ci_lineage_conflict_status_branch_a';

    $branchBObjectEntityId =
        'ci_lineage_conflict_status_branch_b';

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

        try {

            apply_global_fact(
                $pdoB,
                $subjectEntityId,
                $factTypeId,
                $branchBObjectEntityId,
                'ci_lineage_conflicts',
                'Branch B fork attempt',
                [
                    'adjudication_status_classval_id'
                    => governance_default_adjudication_status($pdoB),
                ],
                $initialHeadId
            );

        } catch (RuntimeException $e) {

            if (
                str_contains(
                    $e->getMessage(),
                    'Fact lineage conflict detected'
                )
            ) {
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

        $forked = resolve_canonical_global_fact(
            $pdoA,
            $subjectEntityId,
            $factTypeId,
            $branchBObjectEntityId,
            false
        );

        if ($forked !== null) {
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