<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, 'INGRESS_VIOLATION: ' . $message . PHP_EOL);
    exit(1);
}

function ok(string $message): void
{
    fwrite(STDOUT, 'OK: ' . $message . PHP_EOL);
}

$repoRoot = dirname(__DIR__, 3);

$visibility = require $repoRoot . '/private/framework/contracts/repo_visibility.php';
$policy = require $repoRoot . '/private/framework/contracts/framework_ingress_policy.php';

$visiblePrefixes = $visibility['visible_prefixes'] ?? [];
$visibleFiles = $visibility['visible_files'] ?? [];

$requiredVisibleFiles = $policy['required_visible_files'] ?? [];
$forbiddenPrefixes = $policy['forbidden_visible_prefixes'] ?? [];
$forbiddenFiles = $policy['forbidden_visible_files'] ?? [];

foreach ($requiredVisibleFiles as $path) {
    if (!in_array($path, $visibleFiles, true) && !in_array(dirname($path), $visiblePrefixes, true)) {
        fail("Required framework file not visible: $path");
    }
    ok("Required framework file visible: $path");
}

foreach ($forbiddenPrefixes as $prefix) {
    foreach ($visiblePrefixes as $visible) {
        if ($visible === $prefix || str_starts_with($visible, $prefix . '/')) {
            fail("Forbidden prefix exposed: $prefix");
        }
    }
    ok("Forbidden prefix not exposed: $prefix");
}

foreach ($forbiddenFiles as $file) {
    if (in_array($file, $visibleFiles, true)) {
        fail("Forbidden file exposed: $file");
    }
    ok("Forbidden file not exposed: $file");
}

ok('Framework ingress policy validated');
