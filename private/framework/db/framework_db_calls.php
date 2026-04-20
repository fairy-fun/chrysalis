<?php
declare(strict_types=1);

/**
 * Boundary rule:
 * - public callers use safe wrapper/service functions only
 * - protected DB adapters may be called only from the service layer
 * - raw protected primitives may be called only from this file
 * - CI enforces bypass detection
 *
 * This file owns transport/execution hardening only.
 * It must not contain framework policy/doctrine.
 */

function fw_db_register_system_procedure(PDO $pdo, string $procedureName): void
{
    $sql = 'CALL fw_register_system_procedure(:procedure_name)';
    $stmt = $pdo->prepare($sql);

    fw_assert(
        $stmt instanceof PDOStatement,
        'Failed to prepare statement for fw_register_system_procedure'
    );

    $ok = $stmt->execute([
        ':procedure_name' => $procedureName,
    ]);

    fw_assert(
        $ok === true,
        'Failed to execute fw_register_system_procedure'
    );
}

function fw_db_upsert_system_directive(
    PDO $pdo,
    string $directiveKey,
    string $directiveText,
    string $targetProcedure
): void {
    $sql = 'CALL fw_upsert_system_directive(:directive_key, :directive_text, :target_procedure)';
    $stmt = $pdo->prepare($sql);

    fw_assert(
        $stmt instanceof PDOStatement,
        'Failed to prepare statement for fw_upsert_system_directive'
    );

    $ok = $stmt->execute([
        ':directive_key' => $directiveKey,
        ':directive_text' => $directiveText,
        ':target_procedure' => $targetProcedure,
    ]);

    fw_assert(
        $ok === true,
        'Failed to execute fw_upsert_system_directive'
    );
}