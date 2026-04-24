<?php


declare(strict_types=1);

function validate_entity_traversal_definition(PDO $pdo, array $definition): void
{
    $path = $definition['path'] ?? null;
    if (!is_array($path)) {
        throw new RuntimeException('Traversal definition missing path');
    }

    $pathId = (int)($path['path_id'] ?? 0);
    if ($pathId < 1) {
        throw new RuntimeException('Traversal definition missing valid path_id');
    }

    $traversalId = (int)($path['traversal_id'] ?? 0);
    if ($traversalId < 1) {
        throw new RuntimeException('Traversal definition missing valid traversal_id');
    }

    $rootEntityTypeId = traversal_validator_identifier($path['root_entity_type_id'] ?? null, 'root entity type id');
    $rootTable = traversal_validator_identifier($path['root_table_name'] ?? null, 'root table');

    traversal_validator_assert_table_exists($pdo, $rootTable, 'root table');

    $steps = $definition['steps'] ?? null;
    if (!is_array($steps)) {
        throw new RuntimeException('Traversal definition missing steps');
    }

    $projections = $definition['projections'] ?? null;
    if (!is_array($projections)) {
        throw new RuntimeException('Traversal definition missing projections');
    }

    $ordering = $definition['ordering'] ?? null;
    if (!is_array($ordering)) {
        throw new RuntimeException('Traversal definition missing ordering');
    }

    $knownTables = [$rootTable => true];
    $sequenceIndexes = [];
    $lastSequenceIndex = 0;

    foreach ($steps as $step) {
        if (!is_array($step)) {
            throw new RuntimeException('Traversal step is malformed');
        }

        $sequenceIndex = (int)($step['sequence_index'] ?? 0);
        if ($sequenceIndex < 1) {
            throw new RuntimeException('Traversal step has invalid sequence_index');
        }
        if (isset($sequenceIndexes[$sequenceIndex])) {
            throw new RuntimeException('Traversal step sequence_index is duplicated: ' . $sequenceIndex);
        }
        if ($sequenceIndex <= $lastSequenceIndex) {
            throw new RuntimeException('Traversal step sequence_index is not strictly increasing: ' . $sequenceIndex);
        }
        $sequenceIndexes[$sequenceIndex] = true;
        $lastSequenceIndex = $sequenceIndex;

        $leftTable = traversal_validator_identifier($step['left_table_name'] ?? null, 'step left table');
        $viaTable = traversal_validator_identifier($step['via_table'] ?? null, 'step via table');
        $fromColumn = traversal_validator_identifier($step['from_column'] ?? null, 'step from column');
        $toColumn = traversal_validator_identifier($step['to_column'] ?? null, 'step to column');

        if (!isset($knownTables[$leftTable])) {
            throw new RuntimeException('Traversal step left_table_name is not reachable in chain: ' . $leftTable);
        }

        if (isset($knownTables[$viaTable])) {
            throw new RuntimeException('Traversal step would re-enter an existing table without explicit self-join metadata: ' . $viaTable);
        }

        traversal_validator_assert_table_exists($pdo, $leftTable, 'step left table');
        traversal_validator_assert_table_exists($pdo, $viaTable, 'step via table');
        traversal_validator_assert_column_exists($pdo, $leftTable, $fromColumn, 'step from column');
        traversal_validator_assert_column_exists($pdo, $viaTable, $toColumn, 'step to column');

        $knownTables[$viaTable] = true;
    }

    if ($projections === []) {
        throw new RuntimeException('Traversal definition must include at least one projection');
    }

    foreach ($projections as $projection) {
        if (!is_array($projection)) {
            throw new RuntimeException('Traversal projection is malformed');
        }

        $sourceTable = traversal_validator_identifier($projection['source_table_name'] ?? null, 'projection source table');
        $sourceColumn = traversal_validator_identifier($projection['source_column_name'] ?? null, 'projection source column');
        traversal_validator_identifier($projection['output_name'] ?? null, 'projection output name');

        if (!isset($knownTables[$sourceTable])) {
            throw new RuntimeException('Traversal projection source table is not reachable in alias graph: ' . $sourceTable);
        }

        traversal_validator_assert_column_exists($pdo, $sourceTable, $sourceColumn, 'projection source column');
    }

    foreach ($ordering as $order) {
        if (!is_array($order)) {
            throw new RuntimeException('Traversal ordering is malformed');
        }

        $sourceTable = traversal_validator_identifier($order['source_table_name'] ?? null, 'ordering source table');
        $column = traversal_validator_identifier($order['column_name'] ?? null, 'ordering column');

        if (!isset($knownTables[$sourceTable])) {
            throw new RuntimeException('Traversal ordering source table is not reachable in alias graph: ' . $sourceTable);
        }

        traversal_validator_assert_column_exists($pdo, $sourceTable, $column, 'ordering column');
    }

    unset($rootEntityTypeId);
}

function traversal_validator_identifier(mixed $value, string $label): string
{
    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException('Missing ' . $label);
    }

    $value = trim($value);

    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
        throw new RuntimeException('Invalid ' . $label . ': ' . $value);
    }

    return $value;
}

function traversal_validator_assert_table_exists(PDO $pdo, string $tableName, string $label): void
{
    static $tableCache = [];

    if (array_key_exists($tableName, $tableCache)) {
        if ($tableCache[$tableName] !== true) {
            throw new RuntimeException('Unknown ' . $label . ': ' . $tableName);
        }
        return;
    }

    $stmt = $pdo->prepare(<<<'SQL'
SELECT 1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = :table_name
LIMIT 1
SQL
    );
    $stmt->execute(['table_name' => $tableName]);

    $tableCache[$tableName] = (bool)$stmt->fetchColumn();

    if ($tableCache[$tableName] !== true) {
        throw new RuntimeException('Unknown ' . $label . ': ' . $tableName);
    }
}

function traversal_validator_assert_column_exists(PDO $pdo, string $tableName, string $columnName, string $label): void
{
    static $columnCache = [];

    $cacheKey = $tableName . '.' . $columnName;
    if (array_key_exists($cacheKey, $columnCache)) {
        if ($columnCache[$cacheKey] !== true) {
            throw new RuntimeException('Unknown ' . $label . ': ' . $cacheKey);
        }
        return;
    }

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

    $columnCache[$cacheKey] = (bool)$stmt->fetchColumn();

    if ($columnCache[$cacheKey] !== true) {
        throw new RuntimeException('Unknown ' . $label . ': ' . $cacheKey);
    }
}
