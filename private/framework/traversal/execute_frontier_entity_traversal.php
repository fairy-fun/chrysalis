<?php

declare(strict_types=1);

require_once __DIR__ . '/traversal_definition_validator.php';

function execute_frontier_entity_traversal(PDO $pdo, int $pathId, int $startEntityId): array
{
    if ($pathId < 1) {
        throw new InvalidArgumentException('path_id must be a positive integer');
    }

    if ($startEntityId < 1) {
        throw new InvalidArgumentException('start_entity_id must be a positive integer');
    }

    $steps = load_frontier_entity_traversal_steps($pdo, $pathId);

    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS tmp_frontier');
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS tmp_next_frontier');

    $pdo->exec('CREATE TEMPORARY TABLE tmp_frontier (entity_id BIGINT PRIMARY KEY) ENGINE=MEMORY');
    $pdo->exec('CREATE TEMPORARY TABLE tmp_next_frontier (entity_id BIGINT PRIMARY KEY) ENGINE=MEMORY');

    $seedStmt = $pdo->prepare('INSERT IGNORE INTO tmp_frontier (entity_id) VALUES (:entity_id)');
    $seedStmt->execute(['entity_id' => $startEntityId]);

    foreach ($steps as $step) {
        $pdo->exec('TRUNCATE tmp_next_frontier');

        $sql = emit_frontier_entity_traversal_step_sql($pdo, $step);
        $pdo->exec($sql);

        $nextCount = (int)$pdo->query('SELECT COUNT(*) FROM tmp_next_frontier')->fetchColumn();
        if ($nextCount === 0) {
            break;
        }

        $pdo->exec('DELETE FROM tmp_frontier');
        $pdo->exec('INSERT INTO tmp_frontier (entity_id) SELECT entity_id FROM tmp_next_frontier');
    }

    $frontier = $pdo
        ->query('SELECT entity_id FROM tmp_frontier ORDER BY entity_id')
        ->fetchAll(PDO::FETCH_ASSOC);

    $projections = load_frontier_entity_traversal_projections($pdo, $pathId);

    return [
        'frontier' => $frontier,
        'projections' => $projections,
    ];
}

function load_frontier_entity_traversal_steps(PDO $pdo, int $pathId): array
{
    $stmt = $pdo->prepare(<<<'SQL'
SELECT
    id,
    via_table,
    from_column,
    to_column
FROM sxnzlfun_chrysalis.entity_traversal_steps
WHERE traversal_path_id = :path_id
ORDER BY sequence_index
SQL
    );

    $stmt->execute(['path_id' => $pathId]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($steps as $step) {
        $stepId = (int)($step['id'] ?? 0);
        if ($stepId < 1) {
            throw new RuntimeException('Traversal step missing valid id');
        }

        $viaTable = traversal_validator_identifier($step['via_table'] ?? null, 'step via table');
        $fromColumn = traversal_validator_identifier($step['from_column'] ?? null, 'step from column');
        $toColumn = traversal_validator_identifier($step['to_column'] ?? null, 'step to column');

        traversal_validator_assert_table_exists($pdo, $viaTable, 'step via table');
        traversal_validator_assert_column_exists($pdo, $viaTable, $fromColumn, 'step from column');
        traversal_validator_assert_column_exists($pdo, $viaTable, $toColumn, 'step to column');
    }

    return $steps;
}

function emit_frontier_entity_traversal_step_sql(PDO $pdo, array $step): string
{
    $stepId = (int)($step['id'] ?? 0);
    if ($stepId < 1) {
        throw new RuntimeException('Traversal step missing valid id');
    }

    $viaTable = traversal_validator_identifier($step['via_table'] ?? null, 'step via table');
    $fromColumn = traversal_validator_identifier($step['from_column'] ?? null, 'step from column');
    $toColumn = traversal_validator_identifier($step['to_column'] ?? null, 'step to column');

    $conditions = [
        't.' . $toColumn . ' IS NOT NULL',
        't.' . $toColumn . ' != t.' . $fromColumn,
    ];

    if (frontier_entity_traversal_column_exists($pdo, $viaTable, 'relationship_type')) {
        $conditions[] = <<<SQL
NOT EXISTS (
    SELECT 1
    FROM sxnzlfun_chrysalis.entity_traversal_filters rf
    WHERE rf.traversal_step_id = {$stepId}
      AND rf.filter_type = 'relationship_type_gate'
      AND t.relationship_type != rf.filter_value
)
SQL;
    }

    if (
        frontier_entity_traversal_table_exists($pdo, 'status_history')
        && frontier_entity_traversal_column_exists($pdo, 'status_history', 'entity_id')
        && frontier_entity_traversal_column_exists($pdo, 'status_history', 'status')
    ) {
        $conditions[] = <<<SQL
NOT EXISTS (
    SELECT 1
    FROM sxnzlfun_chrysalis.entity_traversal_filters sf
    WHERE sf.traversal_step_id = {$stepId}
      AND sf.filter_type = 'status_gate'
      AND NOT EXISTS (
          SELECT 1
          FROM sxnzlfun_chrysalis.status_history sh
          WHERE sh.entity_id = t.{$toColumn}
            AND sh.status = sf.filter_value
      )
)
SQL;
    }

    if (frontier_entity_traversal_column_exists($pdo, $viaTable, 'role')) {
        $conditions[] = <<<SQL
NOT EXISTS (
    SELECT 1
    FROM sxnzlfun_chrysalis.entity_traversal_filters rf2
    WHERE rf2.traversal_step_id = {$stepId}
      AND rf2.filter_type = 'role_gate'
      AND rf2.source_table_name = 'calendar_event_participants'
      AND FIND_IN_SET(t.role, rf2.filter_value) = 0
)
SQL;
    }

    return 'INSERT IGNORE INTO tmp_next_frontier (entity_id)' . "\n"
        . 'SELECT DISTINCT t.' . $toColumn . "\n"
        . 'FROM sxnzlfun_chrysalis.' . $viaTable . ' t' . "\n"
        . 'JOIN tmp_frontier f' . "\n"
        . '  ON t.' . $fromColumn . ' = f.entity_id' . "\n"
        . 'WHERE ' . implode("\n  AND ", $conditions);
}

function load_frontier_entity_traversal_projections(PDO $pdo, int $pathId): array
{
    $stmt = $pdo->prepare(<<<'SQL'
SELECT *
FROM sxnzlfun_chrysalis.entity_traversal_projections
WHERE traversal_path_id = :path_id
ORDER BY sequence_index
SQL
    );

    $stmt->execute(['path_id' => $pathId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function frontier_entity_traversal_table_exists(PDO $pdo, string $tableName): bool
{
    traversal_validator_identifier($tableName, 'table name');

    $stmt = $pdo->prepare(<<<'SQL'
SELECT 1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = :table_name
LIMIT 1
SQL
    );
    $stmt->execute(['table_name' => $tableName]);

    return (bool)$stmt->fetchColumn();
}

function frontier_entity_traversal_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    traversal_validator_identifier($tableName, 'table name');
    traversal_validator_identifier($columnName, 'column name');

    $stmt = $pdo->prepare(<<<'SQL'
SELECT 1
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = :table_name
  AND COLUMN_NAME = :column_name
LIMIT 1
SQL
    );
    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    return (bool)$stmt->fetchColumn();
}
