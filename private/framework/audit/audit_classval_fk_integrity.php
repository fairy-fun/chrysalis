<?php

declare(strict_types=1);

function audit_classval_fk_integrity(
    PDO $pdo,
    string $schemaName
): array {

    /*
     * Discover ONLY base-table classval references.
     *
     * Views/projections are intentionally excluded because:
     *
     * - they cannot own FKs
     * - they inherit semantics from source tables
     */

    $sql = "
        SELECT
            c.TABLE_NAME,
            c.COLUMN_NAME,
            k.REFERENCED_TABLE_NAME,
            k.REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS c

        JOIN INFORMATION_SCHEMA.TABLES t
            ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
           AND t.TABLE_NAME = c.TABLE_NAME

        LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
            ON k.TABLE_SCHEMA = c.TABLE_SCHEMA
           AND k.TABLE_NAME = c.TABLE_NAME
           AND k.COLUMN_NAME = c.COLUMN_NAME
           AND k.REFERENCED_TABLE_NAME IS NOT NULL

        WHERE c.TABLE_SCHEMA = :schema
          AND t.TABLE_TYPE = 'BASE TABLE'
          AND c.COLUMN_NAME LIKE '%classval_id'

        ORDER BY
            c.TABLE_NAME,
            c.COLUMN_NAME
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':schema' => $schemaName,
    ]);

    $violations = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $table = (string) $row['TABLE_NAME'];
        $column = (string) $row['COLUMN_NAME'];

        $referencedTable =
            $row['REFERENCED_TABLE_NAME'];

        $referencedColumn =
            $row['REFERENCED_COLUMN_NAME'];

        /*
         * Required doctrine:
         *
         * *_classval_id
         *     ->
         * classvals(id)
         */

        $isValid =
            $referencedTable === 'classvals'
            &&
            $referencedColumn === 'id';

        if ($isValid) {
            continue;
        }

        $violations[] = [
            'table_name' => $table,
            'column_name' => $column,
            'actual_referenced_table'
            => $referencedTable,
            'actual_referenced_column'
            => $referencedColumn,
            'expected_reference'
            => 'classvals(id)',
        ];
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function assert_classval_fk_integrity(
    PDO $pdo,
    string $schemaName
): void {

    $audit = audit_classval_fk_integrity(
        $pdo,
        $schemaName
    );

    if ($audit['ok'] === true) {
        return;
    }

    echo json_encode(
            $audit,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;

    throw new RuntimeException(
        'Classval FK integrity audit failed: violations='
        . (string) $audit['violation_count']
    );
}