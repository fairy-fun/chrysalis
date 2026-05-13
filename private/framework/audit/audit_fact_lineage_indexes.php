<?php

declare(strict_types=1);

function assert_fact_lineage_indexes(
    PDO $pdo,
    string $schemaName
): void {
    $requirements = [
        'entity_linked_facts_global' => [
            'linked_fact_id' => [
                'must_be_unique' => true,
                'description' => 'global lineage identity',
            ],
            'supersedes_linked_fact_id' => [
                'must_be_unique' => true,
                'description' => 'global lineage successor edge',
            ],
        ],
        'entity_linked_facts_event' => [
            'linked_fact_id' => [
                'must_be_unique' => true,
                'description' => 'event lineage identity',
            ],
            'supersedes_linked_fact_id' => [
                'must_be_unique' => true,
                'description' => 'event lineage successor edge',
            ],
        ],
    ];

    $violations = [];

    foreach ($requirements as $tableName => $columns) {
        $tableStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = :schema_name
              AND table_name = :table_name
        ");

        $tableStmt->execute([
            ':schema_name' => $schemaName,
            ':table_name' => $tableName,
        ]);

        if ((int) $tableStmt->fetchColumn() !== 1) {
            $violations[] = [
                'table_name' => $tableName,
                'column_name' => null,
                'violation' => 'missing_table',
            ];

            continue;
        }

        foreach ($columns as $columnName => $requirement) {
            $columnStmt = $pdo->prepare("
                SELECT
                    is_nullable,
                    column_key
                FROM information_schema.columns
                WHERE table_schema = :schema_name
                  AND table_name = :table_name
                  AND column_name = :column_name
                LIMIT 1
            ");

            $columnStmt->execute([
                ':schema_name' => $schemaName,
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);

            $column = $columnStmt->fetch(PDO::FETCH_ASSOC);

            if ($column === false) {
                $violations[] = [
                    'table_name' => $tableName,
                    'column_name' => $columnName,
                    'violation' => 'missing_column',
                    'description' => $requirement['description'],
                ];

                continue;
            }

            if (
                $columnName === 'linked_fact_id'
                && strtoupper((string) $column['is_nullable']) !== 'NO'
            ) {
                $violations[] = [
                    'table_name' => $tableName,
                    'column_name' => $columnName,
                    'violation' => 'nullable_lineage_primary_identity',
                    'description' => $requirement['description'],
                ];
            }

            $indexStmt = $pdo->prepare("
                SELECT
                    index_name,
                    non_unique,
                    seq_in_index
                FROM information_schema.statistics
                WHERE table_schema = :schema_name
                  AND table_name = :table_name
                  AND column_name = :column_name
                ORDER BY
                    non_unique ASC,
                    seq_in_index ASC,
                    index_name ASC
            ");

            $indexStmt->execute([
                ':schema_name' => $schemaName,
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);

            $indexes = $indexStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($indexes === []) {
                $violations[] = [
                    'table_name' => $tableName,
                    'column_name' => $columnName,
                    'violation' => 'missing_index',
                    'description' => $requirement['description'],
                ];

                continue;
            }

            $hasUniqueSingleColumnIndex = false;

            foreach ($indexes as $index) {
                $countStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM information_schema.statistics
                    WHERE table_schema = :schema_name
                      AND table_name = :table_name
                      AND index_name = :index_name
                ");

                $countStmt->execute([
                    ':schema_name' => $schemaName,
                    ':table_name' => $tableName,
                    ':index_name' => $index['index_name'],
                ]);

                if (
                    (int) $index['non_unique'] === 0
                    && (int) $index['seq_in_index'] === 1
                    && (int) $countStmt->fetchColumn() === 1
                ) {
                    $hasUniqueSingleColumnIndex = true;
                    break;
                }
            }

            if (
                $requirement['must_be_unique']
                && !$hasUniqueSingleColumnIndex
            ) {
                $violations[] = [
                    'table_name' => $tableName,
                    'column_name' => $columnName,
                    'violation' => 'missing_unique_single_column_index',
                    'description' => $requirement['description'],
                ];
            }
        }
    }

    if ($violations !== []) {
        throw new RuntimeException(
            'Fact lineage index audit failed: violations='
            . count($violations)
            . PHP_EOL
            . json_encode(
                [
                    'ok' => false,
                    'schema_name' => $schemaName,
                    'violation_count' => count($violations),
                    'violations' => $violations,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }

    echo json_encode(
        [
            'ok' => true,
            'schema_name' => $schemaName,
            'checked_tables' => array_keys($requirements),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
}
