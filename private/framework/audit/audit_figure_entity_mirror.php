<?php

declare(strict_types=1);

function audit_figure_entity_mirror(PDO $pdo, string $schemaName): array
{
    $missingSql = "SELECT DISTINCT f.classval_id AS figure_id FROM {$schemaName}.figures f LEFT JOIN {$schemaName}.entities e ON e.id = f.classval_id WHERE f.classval_id IS NOT NULL AND f.classval_id <> '' AND e.id IS NULL";

    $wrongTypeSql = "SELECT DISTINCT f.classval_id AS figure_id, e.entity_type_id FROM {$schemaName}.figures f JOIN {$schemaName}.entities e ON e.id = f.classval_id WHERE f.classval_id IS NOT NULL AND f.classval_id <> '' AND e.entity_type_id <> 'entity_type_figure'";

    $missing = $pdo->query($missingSql)->fetchAll(PDO::FETCH_ASSOC);
    $wrong = $pdo->query($wrongTypeSql)->fetchAll(PDO::FETCH_ASSOC);

    return [
        'ok' => count($missing) === 0 && count($wrong) === 0,
        'missing_entity_count' => count($missing),
        'wrong_type_count' => count($wrong),
    ];
}

function assert_figure_entity_mirror(PDO $pdo, string $schemaName): void
{
    $audit = audit_figure_entity_mirror($pdo, $schemaName);
    if ($audit['ok']) return;
    throw new RuntimeException('Figure entity mirror audit failed');
}
