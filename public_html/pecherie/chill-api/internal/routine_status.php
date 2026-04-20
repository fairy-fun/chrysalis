<?php
declare(strict_types=1);

require_once '/home/sxnzlfun/private/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    enforce_api_key('admin');

    $pdo = db();
    $databaseName = db_name();

    $expectedProcedures = [
        'apply_classvals_code_rules',
        'ensure_code_normalize_trigger',
        'ensure_code_immutable_trigger',
    ];

    $expectedDefiner = 'sxnzlfun_admin@localhost';

    $procStmt = $pdo->prepare(
        "
        SELECT ROUTINE_NAME, DEFINER
        FROM information_schema.ROUTINES
        WHERE ROUTINE_SCHEMA = :db
          AND ROUTINE_TYPE = 'PROCEDURE'
        "
    );
    $procStmt->execute(['db' => $databaseName]);
    $procedures = $procStmt->fetchAll(PDO::FETCH_ASSOC);

    $procNames = array_values(array_column($procedures, 'ROUTINE_NAME'));
    $missingProcedures = array_values(array_diff($expectedProcedures, $procNames));

    $helperProcedures = array_values(array_filter(
        $procNames,
        static fn(string $name): bool => str_ends_with($name, '_v2')
    ));

    $invalidProcedureDefiners = [];
    foreach ($procedures as $procedure) {
        if (
            in_array($procedure['ROUTINE_NAME'], $expectedProcedures, true)
            && $procedure['DEFINER'] !== $expectedDefiner
        ) {
            $invalidProcedureDefiners[] = [
                'procedure' => $procedure['ROUTINE_NAME'],
                'definer' => $procedure['DEFINER'],
            ];
        }
    }

    $tableStmt = $pdo->prepare(
        "
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :db
          AND TABLE_NAME LIKE '%\\_classvals'
        "
    );
    $tableStmt->execute(['db' => $databaseName]);
    $tables = array_values(array_column($tableStmt->fetchAll(PDO::FETCH_ASSOC), 'TABLE_NAME'));

    $triggerStmt = $pdo->prepare(
        "
        SELECT
            TRIGGER_NAME,
            EVENT_OBJECT_TABLE,
            EVENT_MANIPULATION,
            DEFINER
        FROM information_schema.TRIGGERS
        WHERE TRIGGER_SCHEMA = :db
        "
    );
    $triggerStmt->execute(['db' => $databaseName]);
    $triggers = $triggerStmt->fetchAll(PDO::FETCH_ASSOC);

    $triggersByTable = [];
    foreach ($triggers as $trigger) {
        $tableName = $trigger['EVENT_OBJECT_TABLE'];
        $triggersByTable[$tableName][] = $trigger;
    }

    $tablesMissingTriggers = [];
    $invalidTriggerDefiners = [];

    foreach ($tables as $table) {
        $tableTriggers = $triggersByTable[$table] ?? [];
        $hasInsert = false;
        $hasUpdate = false;

        foreach ($tableTriggers as $trigger) {
            if ($trigger['EVENT_MANIPULATION'] === 'INSERT') {
                $hasInsert = true;
            }

            if ($trigger['EVENT_MANIPULATION'] === 'UPDATE') {
                $hasUpdate = true;
            }

            if ($trigger['DEFINER'] !== $expectedDefiner) {
                $invalidTriggerDefiners[] = [
                    'table' => $table,
                    'trigger' => $trigger['TRIGGER_NAME'],
                    'definer' => $trigger['DEFINER'],
                ];
            }
        }

        if (!$hasInsert || !$hasUpdate) {
            $tablesMissingTriggers[] = $table;
        }
    }

    $proceduresPresentOk = empty($missingProcedures);
    $procedureDefinersOk = empty($invalidProcedureDefiners);
    $triggersOk = empty($tablesMissingTriggers);
    $triggerDefinersOk = empty($invalidTriggerDefiners);

    $overallStatus = (
        $proceduresPresentOk &&
        $procedureDefinersOk &&
        $triggersOk &&
        $triggerDefinersOk
    ) ? 'ok' : 'warning';

    $recommendedAction = null;

    if (!$triggersOk || !$triggerDefinersOk) {
        $recommendedAction = [
            'operation' => 'applyClassvalsCodeRules',
            'method' => 'POST',
            'endpoint' => '/pecherie/chill-api/admin/apply_classvals_code_rules.php',
            'reason' => 'Missing triggers or wrong trigger definers detected.',
        ];
    } elseif (!$proceduresPresentOk || !$procedureDefinersOk) {
        $recommendedAction = [
            'operation' => 'redeployProcedures',
            'method' => 'MANUAL',
            'endpoint' => null,
            'reason' => 'Required procedures are missing or have the wrong definer and must be redeployed from versioned SQL.',
        ];
    }

    json_response(
        [
            'status' => $overallStatus,
            'source' => 'live',
            'database' => $databaseName,
            'summary' => [
                'procedures_present_ok' => $proceduresPresentOk,
                'procedure_definers_ok' => $procedureDefinersOk,
                'triggers_ok' => $triggersOk,
                'trigger_definers_ok' => $triggerDefinersOk,
            ],
            'recommended_action' => $recommendedAction,
            'procedures' => [
                'expected' => $expectedProcedures,
                'missing' => $missingProcedures,
                'invalid_definers' => $invalidProcedureDefiners,
                'unexpected_helpers' => $helperProcedures,
            ],
            'triggers' => [
                'tables_checked' => count($tables),
                'tables_missing_triggers' => $tablesMissingTriggers,
                'invalid_definers' => $invalidTriggerDefiners,
            ],
            'checked_at' => gmdate('c'),
        ],
        200
    );
} catch (Throwable $e) {
    fail(APP_DEBUG ? $e->getMessage() : 'Internal server error', 500);
}