<?php
declare(strict_types=1);

function fw_load_workflow_registry(string $directory): array
{
    $registry = [];

    $pattern = $directory . '/workflow_*_definition.php';

    $files = glob($pattern);

    if ($files === false || count($files) === 0) {
        throw new RuntimeException(
            'No workflow definition files found. Pattern: ' . $pattern
        );
    }

    foreach ($files as $file) {

        $workflow = require $file;

        if (!is_array($workflow)) {
            continue;
        }

        if (!isset($workflow['workflow_id'])) {
            continue;
        }

        if (!isset($workflow['states'])) {
            continue;
        }

        $workflowId = $workflow['workflow_id'];

        $registry[$workflowId] = $workflow;
    }

    return $registry;
}

function fw_get_workflow_definition(
    string $workflowId
): array {

    static $registry = null;

    if ($registry === null) {

        $registry = fw_load_workflow_registry(
            __DIR__
        );
    }

    $workflowId = trim($workflowId);

    if ($workflowId === '') {

        throw new RuntimeException(
            'Workflow id is required.'
        );
    }

    $definition = $registry[$workflowId] ?? null;

    if (!is_array($definition)) {

        throw new RuntimeException(
            'Workflow definition not found: ' .
            $workflowId
        );
    }

    return $definition;
}