<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);

require_once $repoRoot . '/private/framework/api/api_bootstrap.php';
require_once $repoRoot . '/private/framework/facts/apply_fact.php';
require_once $repoRoot . '/private/framework/facts/fact_resolver.php';
require_once $repoRoot . '/private/framework/facts/fact_governance.php';

function validate_fact_resolver_lineage(PDO $pdo): void
{
    $subjectEntityId =
    'ci_fact_resolver_subject_' . bin2hex(random_bytes(8));
    $factTypeId = 'ci_fact_resolver_status';

    $oldObjectEntityId = 'ci_fact_resolver_status';
    $newObjectEntityId = 'ci_fact_resolver_status';

    $pdo->beginTransaction();

    try {

        apply_global_fact(
            $pdo,
            $subjectEntityId,
            $factTypeId,
            $oldObjectEntityId,
            'ci_fact_resolver_lineage',
            'CI resolver lineage old fact',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdo),
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
            throw new RuntimeException(
                'Resolver did not return initial accepted fact'
            );
        }

        if (
            ($oldHead['object_entity_id'] ?? null)
            !==
            $oldObjectEntityId
        ) {
            throw new RuntimeException(
                'Initial canonical head was not the old fact'
            );
        }

        $oldLinkedFactId = (int) (
            $oldHead['linked_fact_id'] ?? 0
        );

        if ($oldLinkedFactId < 1) {
            throw new RuntimeException(
                'Initial canonical head has invalid linked_fact_id'
            );
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
                => governance_default_adjudication_status($pdo),
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
            throw new RuntimeException(
                'Resolver did not return superseding accepted fact'
            );
        }

        if (
            ($newHead['object_entity_id'] ?? null)
            !==
            $oldObjectEntityId
        ) {
            throw new RuntimeException(
                'Canonical head object mismatch'
            );
        }

        if (
            (int) ($newHead['supersedes_linked_fact_id'] ?? 0)
            !==
            $oldLinkedFactId
        ) {
            throw new RuntimeException(
                'Superseding fact does not point to previous linked_fact_id'
            );
        }

        /*
         * Historical fact must still exist.
         * Immutable lineage forbids destructive overwrite semantics.
         */

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
            throw new RuntimeException(
                'Historical superseded fact disappeared'
            );
        }


        /*
         * New fact must resolve canonically.
         */

        $currentCanonical = resolve_canonical_global_fact(
            $pdo,
            $subjectEntityId,
            $factTypeId,
            $oldObjectEntityId,
            true
        );

        if ($currentCanonical === null) {
            throw new RuntimeException(
                'Superseding fact did not resolve canonically'
            );
        }

        if (
            (int) ($currentCanonical['linked_fact_id'] ?? 0)
            !==
            (int) ($newHead['linked_fact_id'] ?? 0)
        ) {
            throw new RuntimeException(
                'Canonical resolver returned unexpected lineage head'
            );
        }

        $pdo->rollBack();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {

    try {

        $pdo = makePdo();

        validate_fact_resolver_lineage($pdo);

        fwrite(
            STDOUT,
            "OK: Fact resolver canonical lineage validation passed\n"
        );

    } catch (Throwable $e) {

        fwrite(
            STDERR,
            'FAIL: ' . $e->getMessage() . PHP_EOL
        );

        exit(1);
    }
}