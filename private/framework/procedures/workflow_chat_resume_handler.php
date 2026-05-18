<?php
declare(strict_types=1);

function fw_resume_workflow_from_chat_reply(
    PDO $pdo,
    array $runtime,
    string $userReply
): array {

    $status = $runtime['status'] ?? null;

    if ($status !== 'awaiting_input') {

        throw new RuntimeException(
            'Runtime is not awaiting input.'
        );
    }

    $workflowId = $runtime['workflow_id'] ?? null;

    if (!is_string($workflowId) || $workflowId === '') {

        throw new RuntimeException(
            'Runtime missing workflow_id.'
        );
    }

    $stateName = $runtime['state_id'] ?? null;

    if (!is_string($stateName) || $stateName === '') {

        throw new RuntimeException(
            'Runtime missing state_id.'
        );
    }

    $expectedInput = $runtime['expected_input'] ?? null;

    if (!is_string($expectedInput) || $expectedInput === '') {

        throw new RuntimeException(
            'Runtime missing expected_input.'
        );
    }

    $input = [
        $expectedInput => trim($userReply),
    ];

    return fw_resume_workflow(
        $pdo,
        $workflowId,
        $stateName,
        $input,
        $runtime['context'] ?? [],
        $runtime['snapshots'] ?? []
    );
}