<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../framework/bootstrap.php';

$pdo = db();

$errors = [];

/*
 * Expression outputs must be POV-scoped through expression_constraint_runs.
 * They describe one character's internal processing, not group emotion.
 */
$sql = <<<'SQL'
SELECT
    eco.id AS output_id,
    ecr.id AS run_id,
    ecr.character_id,
    ecr.context_entity_id
FROM expression_constraint_outputs eco
LEFT JOIN expression_constraint_runs ecr
  ON ecr.id = eco.constraint_run_id
WHERE
    ecr.id IS NULL
    OR ecr.character_id IS NULL
    OR TRIM(ecr.character_id) = ''
    OR ecr.context_entity_id IS NULL
    OR TRIM(ecr.context_entity_id) = ''
ORDER BY eco.id ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Expression output %s is not attached to a valid character POV run.',
        (string)($row['output_id'] ?? '[unknown]')
    );
}

/*
 * Expression outputs must use classval-backed values.
 */
$sql = <<<'SQL'
SELECT
    eco.id AS output_id,
    missing_field
FROM (
    SELECT id, input_value_classval_id AS classval_id, 'input_value_classval_id' AS missing_field
    FROM expression_constraint_outputs
    UNION ALL
    SELECT id, output_value_classval_id, 'output_value_classval_id'
    FROM expression_constraint_outputs
    UNION ALL
    SELECT id, constraint_effect_type_classval_id, 'constraint_effect_type_classval_id'
    FROM expression_constraint_outputs
    UNION ALL
    SELECT id, constraint_strength_classval_id, 'constraint_strength_classval_id'
    FROM expression_constraint_outputs
    UNION ALL
    SELECT id, access_state_classval_id, 'access_state_classval_id'
    FROM expression_constraint_outputs
) eco
LEFT JOIN classvals cv
  ON cv.id = eco.classval_id
WHERE eco.classval_id IS NULL
   OR TRIM(eco.classval_id) = ''
   OR cv.id IS NULL
ORDER BY eco.id ASC, missing_field ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Expression output %s has invalid or missing classval field: %s.',
        (string)($row['output_id'] ?? '[unknown]'),
        (string)($row['missing_field'] ?? '[unknown]')
    );
}

/*
 * Event/sub-event chronology must remain structural.
 * Expression outputs should attach through runs.context_entity_id,
 * not by inventing event columns or projection links.
 */
$sql = <<<'SQL'
SELECT
    ecr.id AS run_id,
    ecr.context_entity_id
FROM expression_constraint_runs ecr
LEFT JOIN entities e
  ON e.id = ecr.context_entity_id
WHERE ecr.context_entity_id LIKE 'calendar_event:%'
  AND e.id IS NULL
ORDER BY ecr.id ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Expression run %s references missing calendar event entity: %s.',
        (string)($row['run_id'] ?? '[unknown]'),
        (string)($row['context_entity_id'] ?? '[unknown]')
    );
}

sort($errors, SORT_STRING);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "OK: expression output validity check passed.\n");
exit(0);