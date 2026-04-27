<?php

declare(strict_types=1);

require_once __DIR__ . '/traversal_definition_loader.php';
require_once __DIR__ . '/traversal_definition_validator.php';
require_once __DIR__ . '/traversal_plan_builder.php';
require_once __DIR__ . '/traversal_sql_emitter.php';
require_once __DIR__ . '/execute_frontier_entity_traversal.php';

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

function resolve_entity_traversal_unified(
    PDO $pdo,
    int $pathId,
    ?int $startEntityId,
    string $mode = 'frontier'
): array {
    if ($pathId < 1) {
        throw new InvalidArgumentException('path_id must be a positive integer');
    }

    if ($mode === 'join') {
        return [
            'mode' => 'join',
            'result' => resolve_entity_traversal_full($pdo, $pathId),
        ];
    }

    if ($mode === 'frontier') {
        if ($startEntityId === null || $startEntityId < 1) {
            throw new InvalidArgumentException('start_entity_id must be a positive integer for frontier mode');
        }

        return [
            'mode' => 'frontier',
            'result' => execute_frontier_entity_traversal($pdo, $pathId, $startEntityId),
        ];
    }

    throw new InvalidArgumentException('Invalid traversal mode');
}