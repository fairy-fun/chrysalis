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
require_once $repoRoot . '/private/framework/facts/apply_fact.php';
require_once $repoRoot . '/private/framework/facts/fact_resolver.php';

try {
    $pdo = makePdo();
} catch (Throwable $e) {
    fail('Could not create PDO: ' . $e->getMessage());
}

echo PHP_EOL;
echo '=== FACT RESOLVER TEST ===' . PHP_EOL;
echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Step 1
|--------------------------------------------------------------------------
| Create initial fact.
*/

$resultA = apply_global_fact(
    $pdo,
    'entity:test_character',
    'fact_type:test_status',
    'status:active',
    'test_script',
    'Initial status fact',
    [
        'adjudication_status_classval_id'
        => 'adjudication_status_approved',
    ]
);

echo 'Inserted Fact A' . PHP_EOL;
print_r($resultA);

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Step 2
|--------------------------------------------------------------------------
| Fetch canonical head after first insert.
*/

$canonicalA = resolve_canonical_global_fact(
    $pdo,
    'entity:test_character',
    'fact_type:test_status'
);

echo 'Canonical after Fact A:' . PHP_EOL;
print_r($canonicalA);

echo PHP_EOL;

if (!isset($canonicalA['linked_fact_id'])) {
    throw new RuntimeException(
        'Failed to resolve canonical fact after Fact A'
    );
}

/*
|--------------------------------------------------------------------------
| Step 3
|--------------------------------------------------------------------------
| Insert superseding fact.
*/

$resultB = apply_global_fact(
    $pdo,
    'entity:test_character',
    'fact_type:test_status',
    'status:inactive',
    'test_script',
    'Superseding status fact',
    [
        'adjudication_status_classval_id'
        => 'adjudication_status_approved',
    ],
    (int) $canonicalA['linked_fact_id']
);

echo 'Inserted Fact B' . PHP_EOL;
print_r($resultB);

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Step 4
|--------------------------------------------------------------------------
| Resolve canonical head again.
*/

$canonicalB = resolve_canonical_global_fact(
    $pdo,
    'entity:test_character',
    'fact_type:test_status'
);

echo 'Canonical after Fact B:' . PHP_EOL;
print_r($canonicalB);

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Step 5
|--------------------------------------------------------------------------
| Verify supersession worked.
*/

if (
    !isset($canonicalB['object_entity_id'])
    || $canonicalB['object_entity_id'] !== 'status:inactive'
) {
    throw new RuntimeException(
        'Canonical resolver failed to return superseding fact'
    );
}

echo 'SUCCESS: resolver returned superseding canonical head.' . PHP_EOL;
echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Step 6
|--------------------------------------------------------------------------
| Verify historical fact still exists.
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM entity_linked_facts_global
    WHERE object_entity_id = :object
    LIMIT 1
");

$stmt->execute([
    'object' => 'status:active',
]);

$historical = $stmt->fetch(PDO::FETCH_ASSOC);

if ($historical === false) {
    throw new RuntimeException(
        'Historical fact disappeared unexpectedly'
    );
}

echo 'SUCCESS: historical fact still exists.' . PHP_EOL;
echo PHP_EOL;

echo '=== TEST COMPLETE ===' . PHP_EOL;
