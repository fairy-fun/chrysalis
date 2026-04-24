<?php


declare(strict_types=1);

require_once __DIR__ . '/../api/api_bootstrap.php';
require_once __DIR__ . '/expression_candidate_reader.php';

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

$characterId = $argv[1] ?? 'CHAR-MAIN-001';

$rows = read_expression_candidates($pdo, $characterId);

echo json_encode([
        'status' => 'ok',
        'database' => $expectedDatabase,
        'character_id' => $characterId,
        'row_count' => count($rows),
        'rows' => $rows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;