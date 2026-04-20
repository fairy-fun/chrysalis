<?php
declare(strict_types=1);

function fw_safe_upsert_system_directive(
    PDO $pdo,
    string $schemaName,
    string $directiveKey,
    string $targetProcedure
): string {
    $directiveText = fw_build_procedure_call_directive_text($schemaName, $targetProcedure);

    fw_validate_procedure_execution_directive(
        $pdo,
        $schemaName,
        $targetProcedure,
        $directiveText
    );

    fw_db_upsert_system_directive(
        $pdo,
        $directiveKey,
        $directiveText,
        $targetProcedure
    );

    fw_audit_log('fw_safe_upsert_system_directive', [
        'directive_key' => $directiveKey,
        'target_procedure' => $targetProcedure,
    ]);

    return $directiveText;
}