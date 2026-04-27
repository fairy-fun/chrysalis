<?php


declare(strict_types=1);

function audit_performance_routine_references(PDO $pdo, string $schemaName): array
{
    $violations = [];

    $sql = <<<SQL
SELECT
    pr.routine_id,
    pr.choreography_type_id,
    c.classval_type_id
FROM {$schemaName}.performance_routines pr
LEFT JOIN {$schemaName}.classvals c
    ON c.id = pr.choreography_type_id
WHERE c.id IS NULL
   OR c.classval_type_id <> 'classval_type_choreography_type'
SQL;

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $violations[] = [
            'rule' => 'performance_routine_choreography_type_classval',
            'routine_id' => $row['routine_id'],
            'value' => $row['choreography_type_id'],
            'expected_classval_type_id' => 'classval_type_choreography_type',
            'actual_classval_type_id' => $row['classval_type_id'],
        ];
    }

    $sql = <<<SQL
SELECT
    pr.routine_id,
    pr.status_classval_id,
    c.classval_type_id
FROM {$schemaName}.performance_routines pr
LEFT JOIN {$schemaName}.classvals c
    ON c.id = pr.status_classval_id
WHERE pr.status_classval_id IS NOT NULL
  AND (
      c.id IS NULL
      OR c.classval_type_id <> 'classval_type_routine_status'
  )
SQL;

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $violations[] = [
            'rule' => 'performance_routine_status_classval',
            'routine_id' => $row['routine_id'],
            'value' => $row['status_classval_id'],
            'expected_classval_type_id' => 'classval_type_routine_status',
            'actual_classval_type_id' => $row['classval_type_id'],
        ];
    }

    return [
        'ok' => count($violations) === 0,
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function assert_performance_routine_references(PDO $pdo, string $schemaName): void
{
    $result = audit_performance_routine_references($pdo, $schemaName);

    if (!$result['ok']) {
        throw new RuntimeException(
            'Performance routine reference audit failed: '
            . json_encode($result, JSON_UNESCAPED_SLASHES)
        );
    }
}