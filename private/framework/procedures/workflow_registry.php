<?php
declare(strict_types=1);

function fw_load_workflow_registry(string $directory): array
{
    $registry = [];

    //$files = glob($directory . '/workflow_*_definition.php');

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