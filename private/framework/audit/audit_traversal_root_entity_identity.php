<?php

declare(strict_types=1);

function audit_traversal_root_entity_identity(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $sql = "
        SELECT
            t.code AS traversal_code,
            etc.base_table_name AS root_table_name,
            c.COLUMN_NAME AS entity_id_column,
            kcu.CONSTRAINT_NAME AS fk_name
        FROM {$schemaName}.entity_traversals t
        JOIN {$schemaName}.entity_type_classvals etc
          ON etc.id = t.root_entity_type_id
        LEFT JOIN information_schema.COLUMNS c
          ON c.TABLE_SCHEMA = :schema_name_columns
         AND c.TABLE_NAME = etc.base_table_name
         AND c.COLUMN_NAME = 'entity_id'
        LEFT JOIN information_schema.KEY_COLUMN_USAGE kcu
          ON kcu.TABLE_SCHEMA = :schema_name_keys
         AND kcu.TABLE_NAME = etc.base_table_name
         AND kcu.COLUMN_NAME = 'entity_id'
         AND kcu.REFERENCED_TABLE_NAME = 'entities'
         AND kcu.REFERENCED_COLUMN_NAME = 'id'
        WHERE t.code IN (
            'TRAVERSAL-MEDLEY-CHOREO',
            'TRAVERSAL-CHARACTER-FULL',
            'TRAVERSAL-RELATIONSHIP-FULL'
        )
          AND (
              c.COLUMN_NAME IS NULL
              OR kcu.CONSTRAINT_NAME IS NULL
          )
        ORDER BY t.code
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':schema_name_columns' => $schemaName,
        ':schema_name_keys' => $schemaName,
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $violations[] = $row;
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function assert_traversal_root_entity_identity(PDO $pdo, string $schemaName): void
{
    $audit = audit_traversal_root_entity_identity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    throw new RuntimeException(
        'Traversal root entity identity audit failed: violations=' .
        (string)$audit['violation_count']
    );
}