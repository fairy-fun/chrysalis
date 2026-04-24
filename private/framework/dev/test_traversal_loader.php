<?php


declare(strict_types=1);

require_once __DIR__ . '/../api/api_bootstrap.php';
require_once __DIR__ . '/../traversal/traversal_definition_loader.php';

try {
    $pdo = makePdo();

    // CHANGE THIS to a real path_id you know exists
    $pathId = 3;

    $definition = load_entity_traversal_definition($pdo, $pathId);

    echo "=== RAW DEFINITION ===\n";
    print_r($definition);

} catch (Throwable $e) {
    echo "ERROR:\n";
    echo $e->getMessage() . "\n";
}
