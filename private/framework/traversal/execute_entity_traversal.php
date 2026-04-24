<?php

declare(strict_types=1);

require_once __DIR__ . '/traversal_definition_validator.php';

function resolve_entity_traversal_full(PDO $pdo, int $pathId): array
{
    $definition = load_entity_traversal_definition($pdo, $pathId);

    validate_entity_traversal_definition($pdo, $definition);

    $plan = build_entity_traversal_plan($definition);
    $sql = emit_entity_traversal_sql($plan);

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'definition' => $definition,
        'plan' => $plan,
        'sql' => $sql,
        'rows' => $rows,
    ];
}

function execute_entity_traversal(PDO $pdo, int $pathId): array
{
    $result = resolve_entity_traversal_full($pdo, $pathId);
    return $result['rows'];
}
