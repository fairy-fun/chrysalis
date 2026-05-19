<?php

declare(strict_types=1);

function fw_start_chat_request(
    PDO    $pdo,
    string $userMessage,
    array  $context = [],
    array  $snapshots = []
): array
{

    $workflowId = fw_match_chat_workflow(
        $userMessage
    );

    if ($workflowId === null) {

        return [
            'status' => 'unmatched',
            'message' => 'No workflow matched request.',
            'user_message' => $userMessage,
        ];
    }

    return fw_start_workflow_from_chat(
        $pdo,
        $userMessage,
        $context,
        $snapshots
    );
}