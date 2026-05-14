<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../private/base_endpoint.php';

try {
    $ctx = endpoint_bootstrap('POST', 'public');

    /** @var PDO $pdo */
    $pdo = $ctx['pdo'];

    $request = $ctx['input'];

    if (!is_array($request)) {
        throw new RuntimeException(
            'Invalid JSON request body.'
        );
    }

    $workflowId = $request['workflow_id'] ?? null;

    if (!is_string($workflowId) || trim($workflowId) === '') {
        throw new RuntimeException(
            'Missing workflow_id.'
        );
    }

    $workflowId = trim($workflowId);

    $registry = $GLOBALS['fw_workflow_registry'] ?? [];

    if (!isset($registry[$workflowId])) {
        throw new RuntimeException(
            'Unknown workflow_id.'
        );
    }

    $workflow = $registry[$workflowId];

    $stateName = $request['state'] ?? ($workflow['initial_state'] ?? null);

    if (!is_string($stateName) || trim($stateName) === '') {
        throw new RuntimeException(
            'Missing state.'
        );
    }

    $stateName = trim($stateName);

    $input = $request['input'] ?? [];

    if (!is_array($input)) {
        throw new RuntimeException(
            'input must be an object.'
        );
    }

    $context = $request['context'] ?? [];

    if (!is_array($context)) {
        throw new RuntimeException(
            'context must be an object.'
        );
    }

    $response = fw_run_workflow_state(
        $pdo,
        $workflowId,
        $stateName,
        $input,
        $context
    );

    json_response($response);

} catch (Throwable $e) {
    fail(400, $e->getMessage());
}