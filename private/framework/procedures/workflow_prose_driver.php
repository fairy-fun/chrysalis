<?php
declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_draft_creator.php';
require_once __DIR__ . '/workflow_value_resolver.php';

function fw_execute_workflow_prose_create_draft(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $result = create_prose_draft(
        $pdo,
        $payload
    );

    return [
        'success' => true,
        'context' => [
            'created_prose' => $result,
        ],
    ];
}