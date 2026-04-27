<?php


declare(strict_types=1);

/*
 * Performance routine identity contract:
 *
 * performance_routines.routine_code is the canonical entity-backed
 * identity for a performance routine.
 *
 * The database enforces reference existence via FK.
 * This audit enforces semantic identity:
 *
 *     entities.entity_type_id = 'entity_type_performance_routine'
 *
 * Locked principle:
 *
 *     Database enforces existence.
 *     CI enforces meaning.
 */

function audit_performance_routine_entity_identity(PDO $pdo, string $schemaName): array
{
    $sql = "
    SELECT
        pr.routine_id,
        pr.routine_code,
        e.id AS entity_id,
        e.entity_type_id
    FROM performance_routines pr
    LEFT JOIN entities e
        ON e.id = pr.routine_code
    WHERE pr.routine_code = ''
       OR e.id IS NULL
       OR e.entity_type_id <> 'entity_type_performance_routine'
    ORDER BY pr.routine_id
    ";

    $stmt = $pdo->query($sql);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'ok' => count($violations) === 0,
        'audit' => 'performance_routine_entity_identity',
        'schema_name' => $schemaName,
        'violations' => $violations,
    ];
}

function assert_performance_routine_entity_identity(PDO $pdo, string $schemaName): void
{
    $result = audit_performance_routine_entity_identity($pdo, $schemaName);

    if ($result['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Performance routine entity identity audit failed: ' .
        json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}