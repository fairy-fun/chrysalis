<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
}

function ok(string $message): void
{
    fwrite(STDOUT, 'OK: ' . $message . PHP_EOL);
}

$repoRoot = dirname(__DIR__, 3);

require_once $repoRoot . '/private/framework/api/api_bootstrap.php';
require_once $repoRoot . '/private/framework/facts/apply_fact.php';
require_once $repoRoot . '/private/framework/facts/fact_lineage.php';
require_once $repoRoot . '/private/framework/facts/fact_resolver.php';
require_once $repoRoot . '/private/framework/facts/fact_governance.php';

try {
    $pdo = makePdo();
} catch (Throwable $e) {
    fail('Could not create PDO: ' . $e->getMessage());
}

try {
    $subjectEntityId = 'ci_lineage_subject';
    $factTypeId = 'ci_lineage_status';

    $objectA = 'ci_lineage_a';
    $objectB = 'ci_lineage_b';

    $pdo->beginTransaction();

    $pdo->prepare("
        DELETE FROM entity_linked_facts_global
        WHERE subject_entity_id = :subject
          AND fact_type_id = :fact_type
    ")->execute([
        ':subject' => $subjectEntityId,
        ':fact_type' => $factTypeId,
    ]);

    apply_global_fact(
        $pdo,
        $subjectEntityId,
        $factTypeId,
        $objectA,
        'ci_lineage',
        'Initial lineage fact',
        [
            'adjudication_status_classval_id'
            => governance_default_adjudication_status(),
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
        throw new RuntimeException('Initial canonical head missing');
    }

    $linkedA = (int) $headA['linked_fact_id'];

    assert_valid_fact_supersession(
        $pdo,
        $linkedA,
        [
            'subject_entity_id' => $subjectEntityId,
            'fact_type_id' => $factTypeId,
        ],
        false
    );

    apply_global_fact(
        $pdo,
        $subjectEntityId,
        $factTypeId,
        $objectB,
        'ci_lineage',
        'Superseding lineage fact',
        [
            'adjudication_status_classval_id'
            => governance_default_adjudication_status(),
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
        throw new RuntimeException('Superseding canonical head missing');
    }

    $linkedB = (int) $headB['linked_fact_id'];

    if ($linkedB === $linkedA) {
        throw new RuntimeException(
            'Supersession did not produce a new canonical head'
        );
    }

    if ((int) $headB['linked_fact_id'] !== $linkedB) {
        throw new RuntimeException(
            'Canonical resolver did not return lineage head'
        );
    }

    if ((int) $headB['supersedes_linked_fact_id'] !== $linkedA) {
        throw new RuntimeException(
            'Canonical head lineage mismatch'
        );
    }

    $ancestors = fact_lineage_ancestors(
        $pdo,
        $linkedB
    );

    if (count($ancestors) !== 2) {
        throw new RuntimeException('Unexpected ancestor count');
    }

    if ((int) $ancestors[0]['linked_fact_id'] !== $linkedB) {
        throw new RuntimeException('Ancestor traversal head mismatch');
    }

    if ((int) $ancestors[1]['linked_fact_id'] !== $linkedA) {
        throw new RuntimeException('Ancestor traversal historical mismatch');
    }

    $successor = fact_lineage_successor(
        $pdo,
        $linkedA
    );

    if ($successor === null) {
        throw new RuntimeException('Expected lineage successor missing');
    }

    if ((int) $successor['linked_fact_id'] !== $linkedB) {
        throw new RuntimeException('Lineage successor mismatch');
    }

    $root = fact_lineage_root(
        $pdo,
        $linkedB
    );

    if ($root === null) {
        throw new RuntimeException('Lineage root missing');
    }

    if ((int) $root['linked_fact_id'] !== $linkedA) {
        throw new RuntimeException('Lineage root mismatch');
    }

    $head = fact_lineage_head(
        $pdo,
        $linkedA
    );

    if ($head === null) {
        throw new RuntimeException('Lineage head missing');
    }

    if ((int) $head['linked_fact_id'] !== $linkedB) {
        throw new RuntimeException('Lineage head mismatch');
    }

    $fullLineage = fact_lineage_full(
        $pdo,
        $linkedB
    );

    if (count($fullLineage) !== 2) {
        throw new RuntimeException('Unexpected full lineage count');
    }

    if ((int) $fullLineage[0]['linked_fact_id'] !== $linkedA) {
        throw new RuntimeException('Full lineage root mismatch');
    }

    if ((int) $fullLineage[1]['linked_fact_id'] !== $linkedB) {
        throw new RuntimeException('Full lineage head mismatch');
    }

    $forkRejected = false;

    try {
        apply_global_fact(
            $pdo,
            $subjectEntityId,
            $factTypeId,
            'ci_lineage_fork_attempt',
            'ci_lineage',
            'Invalid fork attempt',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status(),
            ],
            $linkedA
        );
    } catch (RuntimeException $e) {
        $forkRejected = str_contains(
            $e->getMessage(),
            'not a lineage head'
        );
    }

    if (!$forkRejected) {
        throw new RuntimeException('Write-path fork rejection failed');
    }

    $scopeRejected = false;

    try {
        assert_valid_fact_supersession(
            $pdo,
            $linkedB,
            [
                'subject_entity_id' => 'wrong_subject',
                'fact_type_id' => $factTypeId,
            ],
            false
        );
    } catch (RuntimeException $e) {
        $scopeRejected = str_contains(
            $e->getMessage(),
            'scope mismatch'
        );
    }

    if (!$scopeRejected) {
        throw new RuntimeException('Scope mismatch rejection failed');
    }

    $missingLinkedFactId = (int) $pdo
        ->query("
            SELECT COALESCE(MAX(linked_fact_id), 0) + 1000000
            FROM entity_linked_facts_global
        ")
        ->fetchColumn();

    $orphanRejected = false;

    try {
        assert_valid_fact_supersession(
            $pdo,
            $missingLinkedFactId,
            [
                'subject_entity_id' => $subjectEntityId,
                'fact_type_id' => $factTypeId,
            ],
            false
        );
    } catch (RuntimeException $e) {
        $orphanRejected = str_contains(
            $e->getMessage(),
            'does not exist'
        );
    }

    if (!$orphanRejected) {
        throw new RuntimeException('Orphan prevention failed');
    }

    $pdo->prepare("
        UPDATE entity_linked_facts_global
        SET supersedes_linked_fact_id = :b
        WHERE linked_fact_id = :a
    ")->execute([
        ':a' => $linkedA,
        ':b' => $linkedB,
    ]);

    $cycleRejected = false;

    try {
        fact_lineage_ancestors(
            $pdo,
            $linkedB
        );
    } catch (RuntimeException $e) {
        $cycleRejected = str_contains(
            $e->getMessage(),
            'cycle'
        );
    }

    if (!$cycleRejected) {
        throw new RuntimeException('Cycle rejection failed');
    }

    $pdo->rollBack();

    ok('Fact lineage integrity validation passed');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail($e->getMessage());
}