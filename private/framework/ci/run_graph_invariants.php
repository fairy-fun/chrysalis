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

    /*
     * Entity canonical-label uniqueness invariant.
     *
     * Entity resolution is scoped by entity_type_id.
     * Cross-type duplicate canonical labels are valid.
     * Same-type duplicate canonical labels are invalid and must fail CI.
     */
        $stmt = $pdo->query(
            'SELECT
             entity_type_id,
             canonical_label_normalized,
             COUNT(DISTINCT entity_id) AS entity_count
         FROM entity_texts
         GROUP BY entity_type_id, canonical_label_normalized
         HAVING COUNT(DISTINCT entity_id) > 1'
        );

        $duplicateEntityLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($duplicateEntityLabels !== []) {
            fail(
                'Entity canonical-label uniqueness invariant failed: ' .
                json_encode($duplicateEntityLabels, JSON_UNESCAPED_SLASHES)
            );
        }

        ok('Entity canonical-label uniqueness invariant passed');

    run_all_invariants($pdo, $registry);

    ok('All graph invariants passed');
} catch (Throwable $e) {
    fail('Graph invariant failed: ' . $e->getMessage());
}
