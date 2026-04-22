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

require_once $repoRoot . '/private/framework/api/api_bootstrap.php';
require_once $repoRoot . '/private/framework/classvals/semantic_canonicalisation.php';

try {
    $pdo = makePdo();
} catch (Throwable $e) {
    fail('Could not create PDO: ' . $e->getMessage());
}

try {
    assertAllClassvalSemanticCanonicalRules($pdo);
    ok('Classval semantic canonicalisation passed');
} catch (Throwable $e) {
    fail($e->getMessage());
}