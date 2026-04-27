<?php

declare(strict_types=1);

require_once __DIR__ . '/../traversal/traversal_definition_loader.php';
require_once __DIR__ . '/../traversal/traversal_definition_validator.php';
require_once __DIR__ . '/../traversal/traversal_plan_builder.php';
require_once __DIR__ . '/../traversal/traversal_sql_emitter.php';
require_once __DIR__ . '/../traversal/execute_entity_traversal.php';
require_once __DIR__ . '/../traversal/execute_traversal_by_start_entity.php';

const FW_CHOREOGRAPHY_TRAVERSAL_CODE = 'TRAVERSAL-MEDLEY-CHOREO';

function resolve_choreography_traversal_id(PDO $pdo): int
{
    $stmt = $pdo->prepare(<<<'SQL'
SELECT t.id
FROM sxnzlfun_chrysalis.entity_traversals t
WHERE t.code = :code
LIMIT 1
SQL
    );

    $stmt->execute([
        ':code' => FW_CHOREOGRAPHY_TRAVERSAL_CODE,
    ]);

    $id = $stmt->fetchColumn();

    if ($id === false || (int)$id < 1) {
        throw new RuntimeException('Traversal ' . FW_CHOREOGRAPHY_TRAVERSAL_CODE . ' not found');
    }

    return (int)$id;
}

function run_choreography(PDO $pdo, int $startEntityId): array
{
    if ($startEntityId < 1) {
        throw new InvalidArgumentException('start_entity_id must be a positive integer');
    }

    $traversalId = resolve_choreography_traversal_id($pdo);

    $rows = execute_traversal_by_traversal_id_and_start_entity(
        $pdo,
        $traversalId,
        $startEntityId
    );

    return [
        'traversal_code' => FW_CHOREOGRAPHY_TRAVERSAL_CODE,
        'traversal_id' => $traversalId,
        'start_entity_id' => $startEntityId,
        'row_count' => count($rows),
        'rows' => $rows,
    ];
}
