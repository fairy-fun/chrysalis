<?php
declare(strict_types=1);


function fw_load_workflow_registry(string $directory): array
{
    $registry = [];

    $pattern = $directory . '/workflow_*_definition.php';

    $files = glob($pattern);

    error_log('WORKFLOW GLOB PATTERN: ' . $pattern);
    error_log('WORKFLOW FILES: ' . json_encode($files));

    if ($files === false) {
        error_log('glob() returned false');
        return [];
    }

    foreach ($files as $file) {

        error_log('LOADING WORKFLOW FILE: ' . $file);

        try {

            $workflow = require $file;

            error_log(
                'WORKFLOW LOAD RESULT: ' .
                gettype($workflow)
            );

        } catch (\Throwable $e) {

            error_log(
                'WORKFLOW REQUIRE FAILURE: ' .
                $e->getMessage()
            );

            continue;
        }

        if (!is_array($workflow)) {
            error_log('Workflow not array.');
            continue;
        }

        if (!isset($workflow['workflow_id'])) {
            error_log('Missing workflow_id.');
            continue;
        }

        if (!isset($workflow['states'])) {
            error_log('Missing states.');
            continue;
        }

        $workflowId = $workflow['workflow_id'];

        $registry[$workflowId] = $workflow;

        error_log('REGISTERED WORKFLOW: ' . $workflowId);
    }

    return $registry;
}


function fw_load_workflow_registry2(string $directory): array
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