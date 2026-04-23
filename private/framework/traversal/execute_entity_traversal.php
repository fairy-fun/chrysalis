<?php
function execute_entity_traversal(PDO $pdo, int $pathId): array
{
    $definition = load_entity_traversal_definition($pdo, $pathId);
    $plan = build_entity_traversal_plan($definition);
    $sql = emit_entity_traversal_sql($plan);

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}