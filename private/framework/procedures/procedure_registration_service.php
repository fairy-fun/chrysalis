<?php
declare(strict_types=1);

function fw_safe_register_system_procedure(
    PDO $pdo,
    string $schemaName,
    string $procedureName
): void {
    fw_assert_starts_with(
        FW_ALLOWED_REGISTER_PREFIX,
        $procedureName,
        "Procedure name must start with " . FW_ALLOWED_REGISTER_PREFIX
    );

    fw_assert(
        fw_procedure_exists($pdo, $schemaName, $procedureName),
        "Target procedure does not exist: {$schemaName}.{$procedureName}"
    );

    fw_db_register_system_procedure($pdo, $procedureName);

    fw_audit_log('fw_safe_register_system_procedure', [
        'schema_name' => $schemaName,
        'procedure_name' => $procedureName,
    ]);
}