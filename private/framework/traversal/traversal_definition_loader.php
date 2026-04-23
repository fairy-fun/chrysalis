<?php


declare(strict_types=1);

function load_entity_traversal_definition(PDO $pdo, int $pathId): array
{
    if ($pathId < 1) {
        throw new InvalidArgumentException('path_id must be a positive integer');
    }

    $pathStmt = $pdo->prepare(<<<'SQL'
SELECT
    p.id AS path_id,
    p.traversal_id,
    p.priority,
    t.root_entity_type_id,
    etc.base_table_name AS root_table_name
FROM sxnzlfun_chrysalis.entity_traversal_paths p
JOIN sxnzlfun_chrysalis.entity_traversals t
  ON t.id = p.traversal_id
JOIN sxnzlfun_chrysalis.entity_type_classvals etc
  ON etc.id = t.root_entity_type_id
WHERE p.id = :path_id
SQL
    );

    $pathStmt->execute(['path_id' => $pathId]);
    $path = $pathStmt->fetch(PDO::FETCH_ASSOC);

    if (!$path) {
        throw new RuntimeException('Traversal path not found');
    }

    $stepsStmt = $pdo->prepare(<<<'SQL'
SELECT
    sequence_index,
    left_table_name,
    via_table,
    from_column,
    to_column,
    is_optional
FROM sxnzlfun_chrysalis.entity_traversal_steps
WHERE traversal_path_id = :path_id
ORDER BY sequence_index
SQL
    );

    $stepsStmt->execute(['path_id' => $pathId]);

    $projectionStmt = $pdo->prepare(<<<'SQL'
SELECT
    sequence_index,
    source_table_name,
    source_column_name,
    output_name
FROM sxnzlfun_chrysalis.entity_traversal_projections
WHERE traversal_path_id = :path_id
ORDER BY sequence_index
SQL
    );

    $projectionStmt->execute(['path_id' => $pathId]);

    $orderingStmt = $pdo->prepare(<<<'SQL'
SELECT
    sequence_index,
    source_table_name,
    column_name
FROM sxnzlfun_chrysalis.entity_traversal_ordering
WHERE traversal_path_id = :path_id
ORDER BY sequence_index
SQL
    );

    $orderingStmt->execute(['path_id' => $pathId]);

    return [
        'path' => $path,
        'steps' => $stepsStmt->fetchAll(PDO::FETCH_ASSOC),
        'projections' => $projectionStmt->fetchAll(PDO::FETCH_ASSOC),
        'ordering' => $orderingStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}