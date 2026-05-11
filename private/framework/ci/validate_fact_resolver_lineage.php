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
require_once $repoRoot . '/private/framework/facts/fact_resolver.php';
require_once $repoRoot .
    '/private/framework/facts/fact_governance.php';

try {
    $pdo = makePdo();
} catch (Throwable $e) {
    fail('Could not create PDO: ' . $e->getMessage());
}

try {
    $subjectEntityId = 'ci_fact_resolver_subject';
    $factTypeId = 'ci_fact_resolver_status';
    $oldObjectEntityId = 'ci_fact_resolver_old_status';
    $newObjectEntityId = 'ci_fact_resolver_new_status';

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
        $oldObjectEntityId,
        'ci_fact_resolver_lineage',
        'CI resolver lineage old fact',
        [
            'adjudication_status_classval_id'
            => governance_default_adjudication_status(),
        ]
    );

    $oldHead = resolve_canonical_global_fact(
        $pdo,
        $subjectEntityId,
        $factTypeId,
        null,
        true
    );

    if ($oldHead === null) {
        throw new RuntimeException('Resolver did not return initial accepted fact');
    }

    if (($oldHead['object_entity_id'] ?? null) !== $oldObjectEntityId) {
        throw new RuntimeException('Initial canonical head was not the old fact');
    }

    $oldLinkedFactId = (int) ($oldHead['linked_fact_id'] ?? 0);

    if ($oldLinkedFactId < 1) {
        throw new RuntimeException('Initial canonical head has invalid linked_fact_id');
    }

    apply_global_fact(
        $pdo,
        $subjectEntityId,
        $factTypeId,
        $newObjectEntityId,
        'ci_fact_resolver_lineage',
        'CI resolver lineage new fact',
        [
            'adjudication_status_classval_id'
            => 'adjudication_status_accepted',
        ],
        $oldLinkedFactId
    );

    $newHead = resolve_canonical_global_fact(
        $pdo,
        $subjectEntityId,
        $factTypeId,
        null,
        true
    );

    if ($newHead === null) {
        throw new RuntimeException('Resolver did not return superseding accepted fact');
    }

    if (($newHead['object_entity_id'] ?? null) !== $newObjectEntityId) {
        throw new RuntimeException('Canonical head was not superseding fact');
    }

    if ((int) ($newHead['supersedes_linked_fact_id'] ?? 0) !== $oldLinkedFactId) {
        throw new RuntimeException(
            'Superseding fact does not point to previous linked_fact_id'
        );
    }

    $historicalStmt = $pdo->prepare("
        SELECT linked_fact_id
        FROM entity_linked_facts_global
        WHERE linked_fact_id = :linked_fact_id
        LIMIT 1
    ");

    $historicalStmt->execute([
        ':linked_fact_id' => $oldLinkedFactId,
    ]);

    if ($historicalStmt->fetchColumn() === false) {
        throw new RuntimeException('Historical superseded fact disappeared');
    }

    $pdo->rollBack();

    ok('Fact resolver canonical lineage validation passed');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail($e->getMessage());
}