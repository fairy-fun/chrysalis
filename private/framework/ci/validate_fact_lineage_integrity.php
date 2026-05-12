<?php

declare(strict_types=1);

/*
 * See private/docs/facts/fact_lineage.md for lineage invariants
 * and atomic supersession contract.
 */

$repoRoot = dirname(__DIR__, 3);

require_once $repoRoot . '/private/framework/api/api_bootstrap.php';
require_once $repoRoot . '/private/framework/facts/apply_fact.php';
require_once $repoRoot . '/private/framework/facts/fact_lineage.php';
require_once $repoRoot . '/private/framework/facts/fact_resolver.php';
require_once $repoRoot . '/private/framework/facts/fact_governance.php';

function require_zero_rows(
    PDO $pdo,
    string $sql,
    string $failureMessage
): void {
    $stmt = $pdo->query($sql);

    if ($stmt === false) {
        throw new RuntimeException(
            'Failed to execute audit query'
        );
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows !== []) {
        $count = count($rows);

        throw new RuntimeException(
            $failureMessage .
            ' count=' . $count .
            ' sample=' . json_encode(
                array_slice($rows, 0, 10)
            )
        );
    }
}

function require_at_least_one_row(
    PDO $pdo,
    string $sql,
    string $failureMessage
): void {
    $stmt = $pdo->query($sql);

    if ($stmt === false) {
        throw new RuntimeException(
            'Failed to execute audit query'
        );
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        throw new RuntimeException($failureMessage);
    }
}

function validate_fact_lineage_integrity(PDO $pdo): void
{
    /*
     * -------------------------------------------------------------------------
     * Runtime lineage behavior validation
     * -------------------------------------------------------------------------
     */

    $subjectEntityId = 'ci_lineage_subject';
    $factTypeId = 'ci_lineage_status';

    $objectA = 'ci_lineage_a';
    $objectB = 'ci_lineage_a';

    $pdo->beginTransaction();

    try {

        apply_global_fact(
            $pdo,
            $subjectEntityId,
            $factTypeId,
            $objectA,
            'ci_lineage',
            'Initial lineage fact',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdo),
            ]
        );

        $headA = resolve_canonical_global_fact(
            $pdo,
            $subjectEntityId,
            $factTypeId,
            null,
            true
        );

        if ($headA === null) {
            throw new RuntimeException(
                'Initial canonical lineage head missing'
            );
        }

        $linkedA = (int) $headA['linked_fact_id'];

        apply_global_fact(
            $pdo,
            $subjectEntityId,
            $factTypeId,
            $objectB,
            'ci_lineage',
            'Superseding lineage fact',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdo),
            ],
            $linkedA
        );

        $headB = resolve_canonical_global_fact(
            $pdo,
            $subjectEntityId,
            $factTypeId,
            null,
            true
        );

        if ($headB === null) {
            throw new RuntimeException(
                'Superseding canonical lineage head missing'
            );
        }

        $linkedB = (int) $headB['linked_fact_id'];

        if ($linkedB === $linkedA) {
            throw new RuntimeException(
                'Supersession did not produce a new lineage head'
            );
        }

        if ((int) $headB['supersedes_linked_fact_id'] !== $linkedA) {
            throw new RuntimeException(
                'Superseding fact lineage mismatch'
            );
        }

        $forkRejected = false;

        try {
            apply_global_fact(
                $pdo,
                $subjectEntityId,
                $factTypeId,
                $objectA,
                'ci_lineage',
                'Invalid fork attempt',
                [
                    'adjudication_status_classval_id'
                    => governance_default_adjudication_status($pdo),
                ],
                $linkedA
            );
        } catch (RuntimeException $e) {
            $forkRejected =
                str_contains(
                    $e->getMessage(),
                    'not a lineage head'
                )
                ||
                str_contains(
                    $e->getMessage(),
                    'Fact lineage conflict detected'
                );
        }

        if (!$forkRejected) {
            throw new RuntimeException(
                'Write-path fork rejection failed'
            );
        }

        $pdo->rollBack();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    /*
     * -------------------------------------------------------------------------
     * Structural invariant audits
     * -------------------------------------------------------------------------
     */

    require_zero_rows(
        $pdo,
        "
        SELECT
            f.subject_entity_id,
            f.fact_type_id,
            COALESCE(f.object_entity_id, '__NULL__') AS object_entity_id,
            COUNT(*) AS heads
        FROM entity_linked_facts_global f
        WHERE NOT EXISTS (
            SELECT 1
            FROM entity_linked_facts_global newer
            WHERE newer.supersedes_linked_fact_id = f.linked_fact_id
        )
        GROUP BY
            f.subject_entity_id,
            f.fact_type_id,
            COALESCE(f.object_entity_id, '__NULL__')
        HAVING COUNT(*) > 1
        ",
        'Multiple canonical global lineages detected'
    );

    require_zero_rows(
        $pdo,
        "
        SELECT
            f.subject_entity_id,
            f.context_entity_id,
            f.fact_type_id,
            COALESCE(f.object_entity_id, '__NULL__') AS object_entity_id,
            COUNT(*) AS heads
        FROM entity_linked_facts_event f
        WHERE NOT EXISTS (
            SELECT 1
            FROM entity_linked_facts_event newer
            WHERE newer.supersedes_linked_fact_id = f.linked_fact_id
        )
        GROUP BY
            f.subject_entity_id,
            f.context_entity_id,
            f.fact_type_id,
            COALESCE(f.object_entity_id, '__NULL__')
        HAVING COUNT(*) > 1
        ",
        'Multiple canonical event lineages detected'
    );

    require_zero_rows(
        $pdo,
        "
        SELECT
            supersedes_linked_fact_id,
            COUNT(*) AS successors
        FROM entity_linked_facts_global
        WHERE supersedes_linked_fact_id IS NOT NULL
        GROUP BY supersedes_linked_fact_id
        HAVING COUNT(*) > 1
        ",
        'Global lineage forks detected'
    );

    require_zero_rows(
        $pdo,
        "
        SELECT
            supersedes_linked_fact_id,
            COUNT(*) AS successors
        FROM entity_linked_facts_event
        WHERE supersedes_linked_fact_id IS NOT NULL
        GROUP BY supersedes_linked_fact_id
        HAVING COUNT(*) > 1
        ",
        'Event lineage forks detected'
    );

    require_zero_rows(
        $pdo,
        "
        SELECT child.linked_fact_id
        FROM entity_linked_facts_global child
        LEFT JOIN entity_linked_facts_global parent
            ON parent.linked_fact_id =
               child.supersedes_linked_fact_id
        WHERE child.supersedes_linked_fact_id IS NOT NULL
          AND parent.linked_fact_id IS NULL
        ",
        'Global orphan supersession references detected'
    );

    require_zero_rows(
        $pdo,
        "
        SELECT child.linked_fact_id
        FROM entity_linked_facts_event child
        LEFT JOIN entity_linked_facts_event parent
            ON parent.linked_fact_id =
               child.supersedes_linked_fact_id
        WHERE child.supersedes_linked_fact_id IS NOT NULL
          AND parent.linked_fact_id IS NULL
        ",
        'Event orphan supersession references detected'
    );

    require_zero_rows(
        $pdo,
        "
        SELECT linked_fact_id
        FROM entity_linked_facts_global
        WHERE supersedes_linked_fact_id = linked_fact_id
        ",
        'Global self-supersession detected'
    );

    require_zero_rows(
        $pdo,
        "
        SELECT linked_fact_id
        FROM entity_linked_facts_event
        WHERE supersedes_linked_fact_id = linked_fact_id
        ",
        'Event self-supersession detected'
    );

    require_zero_rows(
        $pdo,
        "
    SELECT child.linked_fact_id
    FROM entity_linked_facts_global child
    JOIN entity_linked_facts_global parent
        ON parent.linked_fact_id =
           child.supersedes_linked_fact_id
    WHERE child.subject_entity_id
            <> parent.subject_entity_id
       OR child.fact_type_id
            <> parent.fact_type_id
       OR COALESCE(
            child.object_entity_id,
            '__NULL__'
          )
            <>
          COALESCE(
            parent.object_entity_id,
            '__NULL__'
          )
    ",
        'Global cross-slot supersession detected'
    );

    require_zero_rows(
        $pdo,
        "
    SELECT child.linked_fact_id
    FROM entity_linked_facts_event child
    JOIN entity_linked_facts_event parent
        ON parent.linked_fact_id =
           child.supersedes_linked_fact_id
    WHERE child.subject_entity_id
            <> parent.subject_entity_id
       OR child.context_entity_id
            <> parent.context_entity_id
       OR child.fact_type_id
            <> parent.fact_type_id
       OR COALESCE(
            child.object_entity_id,
            '__NULL__'
          )
            <>
          COALESCE(
            parent.object_entity_id,
            '__NULL__'
          )
    ",
        'Event cross-slot supersession detected'
    );

    require_at_least_one_row(
        $pdo,
        "
        SELECT
            index_name
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'entity_linked_facts_global'
          AND non_unique = 0
        GROUP BY index_name
        HAVING
            SUM(
                column_name = 'supersedes_linked_fact_id'
            ) = 1
            AND COUNT(*) = 1
        ",
        'Missing UNIQUE constraint on global supersedes_linked_fact_id'
    );

    require_at_least_one_row(
        $pdo,
        "
        SELECT
            index_name
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'entity_linked_facts_event'
          AND column_name = 'supersedes_linked_fact_id'
          AND non_unique = 0
        ",
        'Missing UNIQUE constraint on event supersedes_linked_fact_id'
    );


}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {

    try {

        $pdo = makePdo();

        validate_fact_lineage_integrity($pdo);

        fwrite(
            STDOUT,
            "OK: Fact lineage integrity validation passed\n"
        );

    } catch (Throwable $e) {

        fwrite(
            STDERR,
            'FAIL: ' . $e->getMessage() . PHP_EOL
        );

        exit(1);
    }
}