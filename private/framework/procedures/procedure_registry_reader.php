<?php
declare(strict_types=1);

function fw_fetch_active_registry_entry(PDO $pdo, string $procedureName): ?array
{
    $sql = "
        SELECT procedure_name, is_active
        FROM system_procedure_registry
        WHERE procedure_name = :procedure_name
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':procedure_name' => $procedureName,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fw_is_registered_active_procedure(PDO $pdo, string $procedureName): bool
{
    $row = fw_fetch_active_registry_entry($pdo, $procedureName);
    if ($row === null) {
        return false;
    }

    return (int)($row['is_active'] ?? 0) === 1;
}

function fw_procedure_exists(PDO $pdo, string $schemaName, string $procedureName): bool
{
    $sql = "
        SELECT 1
        FROM information_schema.ROUTINES
        WHERE ROUTINE_SCHEMA = :schema_name
          AND ROUTINE_NAME = :procedure_name
          AND ROUTINE_TYPE = 'PROCEDURE'
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':schema_name' => $schemaName,
        ':procedure_name' => $procedureName,
    ]);

    return (bool)$stmt->fetchColumn();
}