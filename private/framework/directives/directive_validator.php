<?php
declare(strict_types=1);

function fw_validate_procedure_execution_directive(
    PDO $pdo,
    string $schemaName,
    string $targetProcedure,
    string $directiveText
): void {
    fw_assert_starts_with(
        FW_ALLOWED_DIRECTIVE_TARGET_PREFIX,
        $targetProcedure,
        "Directive target procedure must start with " . FW_ALLOWED_DIRECTIVE_TARGET_PREFIX
    );

    $expectedText = fw_build_procedure_call_directive_text($schemaName, $targetProcedure);

    fw_assert(
        $directiveText === $expectedText,
        "Directive text must exactly equal canonical procedure call text"
    );

    fw_assert(
        fw_procedure_exists($pdo, $schemaName, $targetProcedure),
        "Referenced procedure does not exist: {$schemaName}.{$targetProcedure}"
    );

    fw_assert(
        fw_is_registered_active_procedure($pdo, $targetProcedure),
        "Referenced procedure is not active in system_procedure_registry: {$targetProcedure}"
    );
}