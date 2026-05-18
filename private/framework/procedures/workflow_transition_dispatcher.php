<?php
declare(strict_types=1);

function fw_dispatch_workflow_transition(
    array $transition,
    bool $success,
    array $input = [],
    array $context = []
): ?string {

    $driver = $transition['driver'] ?? 'boolean';

    $drivers = [
        'boolean' => 'fw_resolve_boolean_workflow_transition',
    ];

    if (!isset($drivers[$driver])) {
        throw new RuntimeException(
            "Unknown workflow transition driver: {$driver}"
        );
    }

    $function = $drivers[$driver];

    return $function(
        $transition,
        $success,
        $input,
        $context
    );
}