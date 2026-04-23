<?php


declare(strict_types=1);

require_once __DIR__ . '/../api/api_bootstrap.php';
require_once __DIR__ . '/../traversal/traversal_definition_loader.php';
require_once __DIR__ . '/../traversal/traversal_plan_builder.php';
require_once __DIR__ . '/../traversal/traversal_sql_emitter.php';

try {
    $pdo = makePdo();

    $definition = load_entity_traversal_definition($pdo, 3);
    $plan = build_entity_traversal_plan($definition);
    $sql = emit_entity_traversal_sql($plan);

    echo "=== COMPILED SQL ===\n";
    echo $sql . "\n";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    print_r($rows);

} catch (Throwable $e) {
    echo "ERROR:\n";
    echo $e->getMessage() . "\n";
}

