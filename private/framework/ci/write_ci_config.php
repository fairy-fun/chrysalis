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

function require_env(string $name): string
{
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        fail('Missing required environment variable: ' . $name);
    }

    return trim($value);
}

$repoRoot = dirname(__DIR__, 3);
$visibilityFile = $repoRoot . '/private/framework/contracts/repo_visibility.php';
$configPath = $repoRoot . '/pecherie_config.php';

if (!is_file($visibilityFile)) {
    fail('Missing repo_visibility.php');
}

$dbHost = require_env('PECHERIE_DB_HOST');
$dbPort = require_env('PECHERIE_DB_PORT');
$dbName = require_env('PECHERIE_DB_NAME');
$dbUser = require_env('PECHERIE_DB_USER');
$dbPass = require_env('PECHERIE_DB_PASS');

$contents = <<<PHP
<?php

declare(strict_types=1);

\$visibility = require __DIR__ . '/private/framework/contracts/repo_visibility.php';

return [
    'pecherie_api_key' => 'ci-test-key',
    'chrysalis_repo_root' => __DIR__,
    'chrysalis_repo_visible_prefixes' => \$visibility['visible_prefixes'],
    'chrysalis_repo_visible_files' => \$visibility['visible_files'],
    'db' => [
        'host' => %s,
        'port' => %s,
        'name' => %s,
        'user' => %s,
        'pass' => %s,
        'charset' => 'utf8mb4',
    ],
];
PHP;

$rendered = sprintf(
    $contents,
    var_export($dbHost, true),
    var_export($dbPort, true),
    var_export($dbName, true),
    var_export($dbUser, true),
    var_export($dbPass, true)
);

if (file_put_contents($configPath, $rendered . PHP_EOL) === false) {
    fail('Unable to write CI pecherie_config.php');
}

ok('Wrote CI pecherie_config.php');