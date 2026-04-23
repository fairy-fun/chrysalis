<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/api_bootstrap.php';
require_once __DIR__ . '/../traversal/traversal_definition_loader.php';
require_once __DIR__ . '/../traversal/traversal_plan_builder.php';

try {
    $pdo = makePdo();

    $definition = load_entity_traversal_definition($pdo, 3);
    $plan = build_entity_traversal_plan($definition);

    echo "=== TRAVERSAL PLAN ===\n";
    print_r($plan);

} catch (Throwable $e) {
    echo "ERROR:\n";
    echo $e->getMessage() . "\n";
}