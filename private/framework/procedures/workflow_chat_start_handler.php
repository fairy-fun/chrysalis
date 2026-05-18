<?php
declare(strict_types=1);

function fw_start_workflow_from_chat(
    PDO $pdo,
    string $workflowId,
    string $userMessage,
    array $context = [],
    array $snapshots = []
): array {

    $workflowId = trim($workflowId);
    $userMessage = trim($userMessage);

    if ($workflowId === '') {
        throw new RuntimeException('Workflow id is required.');
    }

    $definition = fw_get_workflow_definition($workflowId);

    $entryState = $definition['entry_state'] ?? null;

    if (!is_string($entryState) || trim($entryState) === '') {
        throw new RuntimeException(
            'Workflow definition missing entry_state.'
        );
    }

    return fw_resume_workflow(
        $pdo,
        $workflowId,
        trim($entryState),
        [
            'user_message' => $userMessage,
        ],
        $context,
        $snapshots
    );
}