<?php
declare(strict_types=1);

/**
 * Boundary rule:
 * - public callers use safe wrapper/service functions only
 * - protected DB primitives may be called only from this file
 * - CI enforces bypass detection
 */

function fw_db_register_system_procedure(PDO $pdo, string $procedureName): void
{
    $stmt = $pdo->prepare('CALL fw_register_system_procedure(:procedure_name)');
    $stmt->execute([
        ':procedure_name' => $procedureName,
    ]);
}

function fw_db_upsert_system_directive(
    PDO $pdo,
    string $directiveKey,
    string $directiveText,
    string $targetProcedure
): void {
    $stmt = $pdo->prepare(
        'CALL fw_upsert_system_directive(:directive_key, :directive_text, :target_procedure)'
    );

    $stmt->execute([
        ':directive_key' => $directiveKey,
        ':directive_text' => $directiveText,
        ':target_procedure' => $targetProcedure,
    ]);
}