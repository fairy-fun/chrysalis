<?php
declare(strict_types=1);

function fw_load_workflow_registry(string $directory): array
{
    $registry = [];

    $files = glob($directory . '/*.php');

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