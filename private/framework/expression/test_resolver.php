<?php


declare(strict_types=1);

require_once __DIR__ . '/../api/api_bootstrap.php';
require_once __DIR__ . '/expression_output_resolver.php';

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

$characterId = $argv[1] ?? 'CI_CHAR_EXPR_1';
$domainId = $argv[2] ?? null;

$result = resolve_character_expression_output($pdo, $characterId, $domainId);

echo json_encode([
        'status' => 'ok',
        'database' => $expectedDatabase,
        'result' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;