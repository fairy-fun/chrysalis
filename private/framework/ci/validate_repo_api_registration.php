<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
    exit(1);
}

function ok(string $message): void
{
    fwrite(STDOUT, "OK: {$message}" . PHP_EOL);
}

$contractPath = $repoRoot . '/private/framework/contracts/repo_contract.php';
$routerPath = $repoRoot . '/public_html/pecherie/chill-api/index.php';

if (!is_file($contractPath)) {
    fail('Missing repo contract file');
}

if (!is_file($routerPath)) {
    fail('Missing API router entrypoint');
}

require $contractPath;

if (!defined('FW_REPO_CONTRACT') || !is_array(FW_REPO_CONTRACT)) {
    fail('FW_REPO_CONTRACT is missing or invalid');
}

$operations = FW_REPO_CONTRACT['api_operations'] ?? null;
if (!is_array($operations) || $operations === []) {
    fail('FW_REPO_CONTRACT[api_operations] is missing or empty');
}

$router = file_get_contents($routerPath);
if ($router === false) {
    fail('Unable to read API router entrypoint');
}

$declaredNames = array_keys($operations);
sort($declaredNames);

foreach ($operations as $operation => $meta) {
    if (!is_string($operation) || $operation === '') {
        fail('Encountered invalid operation name in contract');
    }

    if (!is_array($meta)) {
        fail("Operation metadata must be an array for {$operation}");
    }

    $handler = $meta['handler'] ?? null;
    if (!is_string($handler) || $handler === '') {
        fail("Missing handler path for {$operation}");
    }

    $handlerPath = $repoRoot . '/' . $handler;
    if (!is_file($handlerPath)) {
        fail("Declared handler does not exist for {$operation}: {$handler}");
    }

    $isReferenceOperation = str_starts_with(
        $handler,
        'public_html/pecherie/chill-api/reference/'
    );

    if ($isReferenceOperation) {
        if (
            str_contains($router, 'repo_visibility.php')
            && str_contains($router, 'required_operations')
            && str_contains($router, 'public_html/pecherie/chill-api/reference/')
        ) {
            continue;
        }

        fail("Declared reference operation is not dynamically routed in index.php: {$operation}");
    }

    $caseNeedle = "case '{$operation}':";
    if (strpos($router, $caseNeedle) === false) {
        fail("Declared operation is not routed in index.php: {$operation}");
    }
}

ok('All declared operations have handlers and router entries');

preg_match_all("/case\s+'([^']+)'\s*:/", $router, $matches);
$routedNames = $matches[1] ?? [];
$routedNames = array_values(array_unique($routedNames));
sort($routedNames);

$undeclared = array_diff($routedNames, $declaredNames);
if ($undeclared !== []) {
    fail('Router exposes undeclared operations: ' . implode(', ', $undeclared));
}

ok('Router does not expose undeclared operations');

$untested = [];
foreach ($operations as $operation => $meta) {
    $behaviourTested = $meta['behaviour_tested'] ?? false;
    if ($behaviourTested !== true) {
        $untested[] = $operation;
    }
}

if ($untested !== []) {
    fwrite(
        STDOUT,
        'WARN: Operations without behaviour_tested=true: ' . implode(', ', $untested) . PHP_EOL
    );
}

ok('API registration invariant validation completed');