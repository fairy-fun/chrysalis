<?php

declare(strict_types=1);

function audit_classval_uniqueness(PDO $pdo, string $schemaName): array
{
    $duplicateTypeCodeSql = "
        SELECT
            c.classval_type_id,
            c.code,
            COUNT(*) AS duplicate_count,
            GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS classval_ids
        FROM {$schemaName}.classvals c
        GROUP BY c.classval_type_id, c.code
        HAVING COUNT(*) > 1
        ORDER BY c.classval_type_id, c.code
    ";

    $duplicateTypeCodeStmt = $pdo->query($duplicateTypeCodeSql);
    $duplicateTypeCodes = $duplicateTypeCodeStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'ok' => count($duplicateTypeCodes) === 0,
        'schema_name' => $schemaName,
        'duplicate_type_code_count' => count($duplicateTypeCodes),
        'duplicate_type_codes' => $duplicateTypeCodes,
    ];
}

function assert_classval_uniqueness(PDO $pdo, string $schemaName): void
{
    $audit = audit_classval_uniqueness($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Classval uniqueness audit failed: duplicate type/code pairs='
        . (string)$audit['duplicate_type_code_count']
    );
}