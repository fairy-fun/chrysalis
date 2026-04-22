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
$visibilityFile = $repoRoot . '/private/framework/contracts/repo_visibility.php';
$configPath = $repoRoot . '/pecherie_config.php';

if (!is_file($visibilityFile)) {
    fail('Missing repo_visibility.php');
}

$contents = <<<'PHP'
<?php

declare(strict_types=1);

$visibility = require __DIR__ . '/private/framework/contracts/repo_visibility.php';

return [
    'pecherie_api_key' => 'ci-test-key',
    'chrysalis_repo_root' => __DIR__,
    'chrysalis_repo_visible_prefixes' => $visibility['visible_prefixes'],
    'chrysalis_repo_visible_files' => $visibility['visible_files'],
];
PHP;

if (file_put_contents($configPath, $contents . PHP_EOL) === false) {
    fail('Unable to write CI pecherie_config.php');
}

ok('Wrote CI pecherie_config.php');