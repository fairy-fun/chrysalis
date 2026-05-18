<?php

declare(strict_types=1);

function fw_execute_workflow_db_select_one(
    PDO   $pdo,
    array $action,
    array $input = [],
    array $context = []
): array
{

    $sql = $action['sql'] ?? null;

    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException(
            'Missing SQL definition.'
        );
    }

    $bindings = [];

    foreach (($action['bindings'] ?? []) as $key => $value) {

        $bindings[$key] = fw_resolve_workflow_value(
            $value,
            $input,
            $context
        );
    }

    $stmt = $pdo->prepare($sql);

    $stmt->execute($bindings);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'success' => $row !== false,

        'context' => $row !== false
            ? fw_write_workflow_context(
                $context,
                $action['store'] ?? 'row',
                $row
            )
            : $context,
    ];
}