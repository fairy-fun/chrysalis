<?php
declare(strict_types=1);

function fw_execute_workflow_driver_operation(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $driver = $action['driver'] ?? null;
    $operation = $action['operation'] ?? null;

    if (!is_string($driver) || $driver === '') {
        throw new RuntimeException(
            'Missing workflow action driver.'
        );
    }

    if (!is_string($operation) || $operation === '') {
        throw new RuntimeException(
            'Missing workflow action operation.'
        );
    }

    $map = [

        'db' => [

            'select_one' => 'fw_execute_workflow_db_select_one',

        ],

        'prose' => [

            'create_draft' => 'fw_execute_workflow_prose_create_draft',

        ],

    ];

    $handler = $map[$driver][$operation] ?? null;

    if (!is_string($handler) || !function_exists($handler)) {

        throw new RuntimeException(
            "Unsupported workflow driver operation: {$driver}.{$operation}"
        );
    }

    return $handler(
        $pdo,
        $action,
        $input,
        $context
    );
}