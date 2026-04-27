<?php

declare(strict_types=1);

function audit_traversal_trigger_absence(PDO $pdo, string $schemaName): array
{
    $triggerSql = "
        SELECT TRIGGER_NAME
        FROM INFORMATION_SCHEMA.TRIGGERS
        WHERE TRIGGER_SCHEMA = :schema_name
        ORDER BY TRIGGER_NAME
    ";

    $stmt = $pdo->prepare($triggerSql);
    $stmt->execute([
        ':schema_name' => $schemaName,
    ]);

    $triggers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $routineSql = "
        SELECT ROUTINE_NAME
        FROM INFORMATION_SCHEMA.ROUTINES
        WHERE ROUTINE_SCHEMA = :schema_name
          AND ROUTINE_NAME IN (
              'ensure_code_immutable_trigger'
          )
        ORDER BY ROUTINE_NAME
    ";

    $stmt = $pdo->prepare($routineSql);
    $stmt->execute([
        ':schema_name' => $schemaName,
    ]);

    $legacyRoutines = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return [
        'ok' => count($triggers) === 0 && count($legacyRoutines) === 0,
        'schema_name' => $schemaName,
        'unexpected_trigger_count' => count($triggers),
        'unexpected_triggers' => $triggers,
        'legacy_routine_count' => count($legacyRoutines),
        'legacy_routines' => $legacyRoutines,
    ];
}

function assert_traversal_trigger_absence(PDO $pdo, string $schemaName): void
{
    $audit = audit_traversal_trigger_absence($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    $parts = [];

    if ($audit['unexpected_trigger_count'] > 0) {
        $parts[] = 'Forbidden DB triggers found: '
            . implode(', ', $audit['unexpected_triggers']);
    }

    if ($audit['legacy_routine_count'] > 0) {
        $parts[] = 'Legacy trigger-management routines found: '
            . implode(', ', $audit['legacy_routines']);
    }

    throw new RuntimeException(implode("\n", $parts));
}