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

        if (!isset($workflow['states']) && !isset($workflow['delegates_to'])) {
            continue;
        }

        $workflowId = $workflow['workflow_id'];

        $registry[$workflowId] = $workflow;
    }

    return $registry;
}

function fw_resolve_delegated_workflow_definition(
    array $registry,
    string $workflowId
): array {

    $definition = $registry[$workflowId] ?? null;

    if (!is_array($definition)) {

        throw new RuntimeException(
            'Workflow definition not found: ' .
            $workflowId
        );
    }

    $delegatesTo = $definition['delegates_to'] ?? null;

    if (!is_string($delegatesTo) || trim($delegatesTo) === '') {
        return $definition;
    }

    $delegatesTo = trim($delegatesTo);

    if ($delegatesTo === $workflowId) {
        throw new RuntimeException(
            'Workflow cannot delegate to itself: ' .
            $workflowId
        );
    }

    $target = fw_resolve_delegated_workflow_definition(
        $registry,
        $delegatesTo
    );

    $targetInitialContext = $target['initial_context'] ?? [];
    $wrapperInitialContext = $definition['initial_context'] ?? [];

    if (!is_array($targetInitialContext)) {
        $targetInitialContext = [];
    }

    if (!is_array($wrapperInitialContext)) {
        $wrapperInitialContext = [];
    }

    $target['workflow_id'] = $workflowId;
    $target['delegates_to'] = $delegatesTo;
    $target['initial_context'] = array_merge(
        $targetInitialContext,
        $wrapperInitialContext
    );

    return $target;
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

    return fw_resolve_delegated_workflow_definition(
        $registry,
        $workflowId
    );
}
