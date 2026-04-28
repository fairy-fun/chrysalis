<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../framework/bootstrap.php';

$pdo = db();

$errors = [];

/*
 * RULE 1: Exactly one output per constraint run
 * (represents one transformation per POV beat)
 */
$sql = <<<'SQL'
SELECT
    constraint_run_id,
    COUNT(*) AS output_count
FROM expression_constraint_outputs
GROUP BY constraint_run_id
HAVING COUNT(*) > 1
ORDER BY constraint_run_id ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Constraint run %s has %s outputs (expected exactly 1).',
        (string)$row['constraint_run_id'],
        (string)$row['output_count']
    );
}

/*
 * RULE 2: Input and output must differ
 * (otherwise no real transformation occurred)
 */
$sql = <<<'SQL'
SELECT
    id
FROM expression_constraint_outputs
WHERE input_value_classval_id = output_value_classval_id
ORDER BY id ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Expression output %s has identical input and output (no transformation).',
        (string)$row['id']
    );
}

/*
 * RULE 3: POV must be specific (no null / placeholder characters)
 */
$sql = <<<'SQL'
SELECT
    id
FROM expression_constraint_runs
WHERE character_id IS NULL
   OR TRIM(character_id) = ''
   OR character_id LIKE 'group_%'
   OR character_id = 'ALL'
ORDER BY id ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Constraint run %s is not a valid character POV.',
        (string)$row['id']
    );
}

/*
 * RULE 4: Access state must always be defined
 * (prevents silent leakage of interpretation)
 */
$sql = <<<'SQL'
SELECT
    id
FROM expression_constraint_outputs
WHERE access_state_classval_id IS NULL
   OR TRIM(access_state_classval_id) = ''
ORDER BY id ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Expression output %s missing access state.',
        (string)$row['id']
    );
}

sort($errors, SORT_STRING);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "OK: expression semantics check passed.\n");
exit(0);