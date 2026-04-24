<?php


declare(strict_types=1);

function audit_classval_entity_mirror(PDO $pdo, string $schemaName): array
{
    $missingSql = "
        SELECT
            c.id AS classval_id,
            c.classval_type_id,
            c.code
        FROM {$schemaName}.classvals c
        LEFT JOIN {$schemaName}.entities e
            ON e.id = c.id
        WHERE e.id IS NULL
        ORDER BY c.id
    ";

    $wrongTypeSql = "
        SELECT
            c.id AS classval_id,
            c.classval_type_id,
            c.code,
            e.entity_type_id AS actual_entity_type_id
        FROM {$schemaName}.classvals c
        JOIN {$schemaName}.entities e
            ON e.id = c.id
        WHERE e.entity_type_id <> 'entity_type_classval'
        ORDER BY c.id
    ";

    $missingStmt = $pdo->query($missingSql);
    $missingEntities = $missingStmt->fetchAll(PDO::FETCH_ASSOC);

    $wrongTypeStmt = $pdo->query($wrongTypeSql);
    $wrongTypeEntities = $wrongTypeStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'ok' => count($missingEntities) === 0 && count($wrongTypeEntities) === 0,
        'schema_name' => $schemaName,
        'missing_entity_count' => count($missingEntities),
        'wrong_type_count' => count($wrongTypeEntities),
        'missing_entities' => $missingEntities,
        'wrong_type_entities' => $wrongTypeEntities,
    ];
}

function assert_classval_entity_mirror(PDO $pdo, string $schemaName): void
{
    $audit = audit_classval_entity_mirror($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Classval entity mirror audit failed: missing mirrors='
        . (string)$audit['missing_entity_count']
        . ', wrong type='
        . (string)$audit['wrong_type_count']
    );
}