<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../framework/bootstrap.php';

$pdo = db();

$errors = [];

/*
 * Chronology discipline:
 * Multiple expression runs for the same character inside the same parent event
 * must use sub-event contexts, not all point at the parent event.
 *
 * Assumes calendar_events has:
 * - entity_id
 * - chronology_address
 */

$sql = <<<'SQL'
SELECT
    ecr.character_id,
    ce.chronology_address,
    COUNT(*) AS run_count
FROM expression_constraint_runs ecr
JOIN calendar_events ce
  ON ce.entity_id = ecr.context_entity_id
GROUP BY
    ecr.character_id,
    ce.chronology_address
HAVING COUNT(*) > 1
ORDER BY
    ecr.character_id ASC,
    ce.chronology_address ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Character %s has %s expression runs attached to the same chronology address %s. Split emotional shifts into sub-event contexts.',
        (string)$row['character_id'],
        (string)$row['run_count'],
        (string)$row['chronology_address']
    );
}

/*
 * Sub-event chronology format check.
 * Allows:
 *   1.6.2.1
 *   1.6.2.1.1
 *   1.6.2.1.2
 *
 * Rejects malformed addresses.
 */
$sql = <<<'SQL'
SELECT
    id,
    entity_id,
    chronology_address
FROM calendar_events
WHERE chronology_address IS NULL
   OR TRIM(chronology_address) = ''
   OR chronology_address NOT REGEXP '^[0-9]+(\\.[0-9]+){3,}$'
ORDER BY id ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Calendar event %s has invalid chronology address: %s.',
        (string)$row['entity_id'],
        (string)$row['chronology_address']
    );
}

/*
 * Expression runs should point to the most specific available beat.
 * If a parent event has sub-events and the same character has multiple runs
 * under that parent, using the parent directly is ambiguous.
 */
$sql = <<<'SQL'
SELECT
    parent.id AS parent_event_id,
    parent.entity_id AS parent_entity_id,
    parent.chronology_address AS parent_chronology_address,
    ecr.character_id,
    COUNT(child.id) AS child_count
FROM calendar_events parent
JOIN expression_constraint_runs ecr
  ON ecr.context_entity_id = parent.entity_id
JOIN calendar_events child
  ON child.chronology_address LIKE CONCAT(parent.chronology_address, '.%')
WHERE parent.chronology_address REGEXP '^[0-9]+\\.[0-9]+\\.[0-9]+\\.[0-9]+$'
GROUP BY
    parent.id,
    parent.entity_id,
    parent.chronology_address,
    ecr.character_id
HAVING child_count > 0
ORDER BY
    parent.chronology_address ASC,
    ecr.character_id ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $errors[] = sprintf(
        'Character %s has expression run attached to parent event %s, but sub-events exist. Attach emotional shifts to the specific sub-event.',
        (string)$row['character_id'],
        (string)$row['parent_chronology_address']
    );
}

sort($errors, SORT_STRING);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "OK: expression chronology discipline check passed.\n");
exit(0);