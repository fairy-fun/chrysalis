<?php

declare(strict_types=1);

function fail(string $category, string $message): never
{
    fwrite(STDERR, $category . ': ' . $message . PHP_EOL);
    exit(1);
}

function ok(string $message): void
{
    fwrite(STDOUT, 'OK: ' . $message . PHP_EOL);
}

function normalize_relative_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));

    while (strpos($path, '//') !== false) {
        $path = str_replace('//', '/', $path);
    }

    $path = trim($path, '/');

    if ($path === '' || $path === '.') {
        return '';
    }

    $parts = explode('/', $path);
    $clean = [];

    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            fail('CONTRACT_VIOLATION', "Path traversal segment '..' is not allowed: {$path}");
        }
        $clean[] = $part;
    }

    return implode('/', $clean);
}

function assert_declared_paths_exist(array $paths, string $type, string $repoRoot): void
{
    foreach ($paths as $path) {
        if (!is_string($path)) {
            fail('CONTRACT_VIOLATION', "{$type} entry is not a string");
        }

        $normalized = normalize_relative_path($path);
        if ($normalized === '') {
            fail('CONTRACT_VIOLATION', "{$type} entry resolves to empty path");
        }

        $absolute = $repoRoot . '/' . $normalized;

        if ($type === 'visible_prefix') {
            if (!is_dir($absolute)) {
                fail('VISIBILITY_MISMATCH', "Declared visible_prefix does not exist as directory: {$normalized}");
            }
            ok("visible_prefix exists: {$normalized}");
            continue;
        }

        if ($type === 'visible_file') {
            if (!is_file($absolute)) {
                fail('VISIBILITY_MISMATCH', "Declared visible_file does not exist as file: {$normalized}");
            }
            ok("visible_file exists: {$normalized}");
            continue;
        }

        fail('CONTRACT_VIOLATION', "Unknown assertion type: {$type}");
    }
}

function load_switch_cases(string $indexPath): array
{
    $contents = file_get_contents($indexPath);
    if ($contents === false) {
        fail('API_SURFACE_INCOMPLETE', "Unable to read {$indexPath}");
    }

    preg_match_all("/case\\s+'([^']+)'\\s*:/", $contents, $matches);
    return array_values(array_unique($matches[1] ?? []));
}

$repoRoot = dirname(__DIR__, 3);
$visibilityFile = $repoRoot . '/private/framework/contracts/repo_visibility.php';
$indexFile = $repoRoot . '/public_html/pecherie/chill-api/index.php';

if (!is_file($visibilityFile)) {
    fail('CONTRACT_VIOLATION', 'Missing repo visibility contract: private/framework/contracts/repo_visibility.php');
}

$visibility = require $visibilityFile;

if (!is_array($visibility)) {
    fail('CONTRACT_VIOLATION', 'repo_visibility.php must return an array');
}

$visiblePrefixes = $visibility['visible_prefixes'] ?? null;
$visibleFiles = $visibility['visible_files'] ?? null;
$requiredOperations = $visibility['required_operations'] ?? null;

if (!is_array($visiblePrefixes)) {
    fail('CONTRACT_VIOLATION', 'visible_prefixes must be an array');
}

if (!is_array($visibleFiles)) {
    fail('CONTRACT_VIOLATION', 'visible_files must be an array');
}

if (!is_array($requiredOperations)) {
    fail('CONTRACT_VIOLATION', 'required_operations must be an array');
}

assert_declared_paths_exist($visiblePrefixes, 'visible_prefix', $repoRoot);
assert_declared_paths_exist($visibleFiles, 'visible_file', $repoRoot);

$allDeclared = [];
foreach ($visiblePrefixes as $path) {
    $normalized = normalize_relative_path($path);
    if (isset($allDeclared[$normalized])) {
        fail('CONTRACT_VIOLATION', "Duplicate declared visibility path: {$normalized}");
    }
    $allDeclared[$normalized] = 'prefix';
}

foreach ($visibleFiles as $path) {
    $normalized = normalize_relative_path($path);
    if (isset($allDeclared[$normalized])) {
        fail('CONTRACT_VIOLATION', "Path declared in more than one visibility list: {$normalized}");
    }
    $allDeclared[$normalized] = 'file';
}

foreach ($requiredOperations as $operation => $handlerPath) {
    if (!is_string($operation) || $operation === '') {
        fail('CONTRACT_VIOLATION', 'required_operations contains an invalid operation name');
    }

    if (!is_string($handlerPath) || $handlerPath === '') {
        fail('CONTRACT_VIOLATION', "required_operations handler path missing for operation {$operation}");
    }

    $normalizedHandler = normalize_relative_path($handlerPath);
    $absoluteHandler = $repoRoot . '/' . $normalizedHandler;

    if (!is_file($absoluteHandler)) {
        fail('API_SURFACE_INCOMPLETE', "Declared handler for {$operation} does not exist: {$normalizedHandler}");
    }

    ok("handler exists for {$operation}: {$normalizedHandler}");
}

if (!is_file($indexFile)) {
    fail('API_SURFACE_INCOMPLETE', 'Missing API front controller: public_html/pecherie/chill-api/index.php');
}

$indexCases = load_switch_cases($indexFile);

foreach ($requiredOperations as $operation => $handlerPath) {
    if (!in_array($operation, $indexCases, true)) {
        fail('API_SURFACE_INCOMPLETE', "Operation {$operation} is declared but not routed in chill-api/index.php");
    }
    ok("operation routed in index.php: {$operation}");
}

foreach ($indexCases as $operation) {
    if (!array_key_exists($operation, $requiredOperations)) {
        fail('CONTRACT_VIOLATION', "index.php exposes undeclared operation: {$operation}");
    }
}

ok('Repo visibility contract validated successfully');