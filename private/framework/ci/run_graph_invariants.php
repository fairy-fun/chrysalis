<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
}

function ok(string $message): void
{
    fwrite(STDOUT, 'OK: ' . $message . PHP_EOL);
}

$repoRoot = dirname(__DIR__, 3);
$configPath = $repoRoot . '/pecherie_config.php';
$ciConfigPath = $repoRoot . '/pecherie_ci_config.php';

if (!is_file($ciConfigPath) && !is_file($configPath)) {
    fail('Missing config file (run write_ci_config.php or provide an existing server config)');
}

require_once $repoRoot . '/private/framework/api/api_bootstrap.php';
require_once $repoRoot . '/private/framework/invariants/invariant_runner.php';

$registry = require $repoRoot . '/private/framework/invariants/invariant_registry.php';

try {
    $pdo = makePdo();
    verifyExpectedDatabase($pdo);

    run_all_invariants($pdo, $registry);

    ok('All graph invariants passed');
} catch (Throwable $e) {
    fail('Graph invariant failed: ' . $e->getMessage());
}
