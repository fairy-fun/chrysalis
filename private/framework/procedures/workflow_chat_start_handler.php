<?php
declare(strict_types=1);

function fw_start_workflow_from_chat(
    PDO $pdo,
    string $workflowId,
    string $entryState,
    string $userMessage,
    array $context = [],
    array $snapshots = []
): array {

    $workflowId = trim($workflowId);
    $entryState = trim($entryState);
    $userMessage = trim($userMessage);

    if ($workflowId === '') {

        throw new RuntimeException(
            'Workflow id is required.'
        );
    }

    if ($entryState === '') {

        throw new RuntimeException(
            'Workflow entry state is required.'
        );
    }

    return fw_resume_workflow(
        $pdo,
        $workflowId,
        $entryState,
        [
            'user_message' => $userMessage,
        ],
        $context,
        $snapshots
    );
}