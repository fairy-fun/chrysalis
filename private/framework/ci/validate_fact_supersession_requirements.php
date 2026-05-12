<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);

require_once $repoRoot . '/private/framework/api/api_bootstrap.php';
require_once $repoRoot . '/private/framework/facts/apply_fact.php';
require_once $repoRoot . '/private/framework/facts/fact_governance.php';

function validate_fact_supersession_requirements(PDO $pdo): void
{
    $pdo->beginTransaction();

    try {

        /*
         * GLOBAL FACT ENFORCEMENT
         */

        $globalSubjectId =
            'ci_supersession_required_global_' . bin2hex(random_bytes(8));

        $globalFactTypeId = 'ci_supersession_required_status';
        $globalObjectId = 'ci_supersession_required_status';
        $globalNewObjectId = 'ci_supersession_required_status_new';

        $globalInitial = apply_global_fact(
            $pdo,
            $globalSubjectId,
            $globalFactTypeId,
            $globalObjectId,
            'ci_supersession_required',
            'Initial global fact',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdo),
            ]
        );

        $globalFailureObserved = false;

        try {

            apply_global_fact(
                $pdo,
                $globalSubjectId,
                $globalFactTypeId,
                $globalNewObjectId,
                'ci_supersession_required',
                'Illegal global slot advancement',
                [
                    'adjudication_status_classval_id'
                    => governance_default_adjudication_status($pdo),
                ]
            );

        } catch (RuntimeException $e) {

            if (
                str_contains(
                    $e->getMessage(),
                    'Explicit supersedesLinkedFactId is required'
                )
            ) {
                $globalFailureObserved = true;
            } else {
                throw $e;
            }
        }

        if (!$globalFailureObserved) {
            throw new RuntimeException(
                'Global fact slot advancement without explicit supersession did not fail'
            );
        }

        apply_global_fact(
            $pdo,
            $globalSubjectId,
            $globalFactTypeId,
            $globalNewObjectId,
            'ci_supersession_required',
            'Legal global supersession',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdo),
            ],
            (int) $globalInitial['linked_fact_id']
        );

        /*
         * EVENT FACT ENFORCEMENT
         */

        $eventSubjectId =
            'ci_supersession_required_event_' . bin2hex(random_bytes(8));

        $eventContextId =
            'ci_supersession_required_context_' . bin2hex(random_bytes(8));

        $eventFactTypeId = 'ci_supersession_required_status';
        $eventObjectId = 'ci_supersession_required_status';
        $eventNewObjectId = 'ci_supersession_required_status_new';

        $eventInitial = apply_event_fact(
            $pdo,
            $eventSubjectId,
            $eventContextId,
            $eventFactTypeId,
            $eventObjectId,
            'ci_supersession_required',
            'Initial event fact',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdo),
            ]
        );

        $eventFailureObserved = false;

        try {

            apply_event_fact(
                $pdo,
                $eventSubjectId,
                $eventContextId,
                $eventFactTypeId,
                $eventNewObjectId,
                'ci_supersession_required',
                'Illegal event slot advancement',
                [
                    'adjudication_status_classval_id'
                    => governance_default_adjudication_status($pdo),
                ]
            );

        } catch (RuntimeException $e) {

            if (
                str_contains(
                    $e->getMessage(),
                    'Explicit supersedesLinkedFactId is required'
                )
            ) {
                $eventFailureObserved = true;
            } else {
                throw $e;
            }
        }

        if (!$eventFailureObserved) {
            throw new RuntimeException(
                'Event fact slot advancement without explicit supersession did not fail'
            );
        }

        apply_event_fact(
            $pdo,
            $eventSubjectId,
            $eventContextId,
            $eventFactTypeId,
            $eventNewObjectId,
            'ci_supersession_required',
            'Legal event supersession',
            [
                'adjudication_status_classval_id'
                => governance_default_adjudication_status($pdo),
            ],
            (int) $eventInitial['linked_fact_id']
        );

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

        validate_fact_supersession_requirements($pdo);

        fwrite(
            STDOUT,
            "OK: Fact supersession requirement validation passed\n"
        );

    } catch (Throwable $e) {

        fwrite(
            STDERR,
            'FAIL: ' . $e->getMessage() . PHP_EOL
        );

        exit(1);
    }
}