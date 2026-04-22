<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/private/framework/api/api_bootstrap.php';

$pdo = makePdo();

$stmt = $pdo->query("
    SELECT id, code
    FROM classvals
    WHERE id = 'fact_type_event_theme'
       OR code = 'event_theme'
       OR id = 'fact_event_has_theme'
       OR code = 'event_has_theme'
    ORDER BY id, code
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;