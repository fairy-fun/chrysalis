<?php

declare(strict_types=1);

function fw_generate_workflow_session_id(): string
{
    return 'workflow_session:' . bin2hex(random_bytes(16));
}

function fw_load_workflow_session(
    PDO $pdo,
    string $sessionId
): ?array {

    $stmt = $pdo->prepare(
        '
        SELECT
            session_id,
            workflow_id,
            state_id,
            status,
            expected_input,
            context_json,
            snapshots_json
        FROM workflow_sessions
        WHERE session_id = :session_id
        LIMIT 1
        '
    );

    $stmt->execute([
        'session_id' => $sessionId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return null;
    }

    return [
        'session_id' => $row['session_id'],
        'workflow_id' => $row['workflow_id'],
        'state_id' => $row['state_id'],
        'status' => $row['status'],
        'expected_input' => $row['expected_input'],

        'context' => json_decode(
                $row['context_json'],
                true
            ) ?? [],

        'snapshots' => json_decode(
                $row['snapshots_json'],
                true
            ) ?? [],
    ];
}

function fw_store_workflow_session(
    PDO    $pdo,
    string $sessionId,
    string $workflowId,
    string $stateId,
    string $status,
    ?string $expectedInput,
    array  $context,
    array  $snapshots
): void {

    $stmt = $pdo->prepare(
        '
        INSERT INTO workflow_sessions (
            session_id,
            workflow_id,
            state_id,
            status,
            expected_input,
            context_json,
            snapshots_json
        )
        VALUES (
            :session_id,
            :workflow_id,
            :state_id,
            :status,
            :expected_input,
            :context_json,
            :snapshots_json
        )
        ON DUPLICATE KEY UPDATE
            workflow_id = VALUES(workflow_id),
            state_id = VALUES(state_id),
            status = VALUES(status),
            expected_input = VALUES(expected_input),
            context_json = VALUES(context_json),
            snapshots_json = VALUES(snapshots_json)
        '
    );

    $stmt->execute([
        'session_id' => $sessionId,
        'workflow_id' => $workflowId,
        'state_id' => $stateId,
        'status' => $status,
        'expected_input' => $expectedInput,

        'context_json' => json_encode(
            $context,
            JSON_UNESCAPED_UNICODE
        ),

        'snapshots_json' => json_encode(
            $snapshots,
            JSON_UNESCAPED_UNICODE
        ),
    ]);
}

function fw_start_chat_request(
    PDO     $pdo,
    string  $userMessage,
    ?string $sessionId = null
): array {

    /*
    |--------------------------------------------------------------------------
    | Resume existing workflow session
    |--------------------------------------------------------------------------
    */

    if (
        is_string($sessionId)
        && trim($sessionId) !== ''
    ) {

        $session = fw_load_workflow_session(
            $pdo,
            $sessionId
        );

        if ($session === null) {

            return [
                'status' => 'error',
                'message' => 'Workflow session not found.',
                'session_id' => $sessionId,
            ];
        }

        $input = [];

        if (
            is_string($session['expected_input'])
            && $session['expected_input'] !== ''
        ) {

            $input[
            $session['expected_input']
            ] = $userMessage;
        }

        $result = fw_resume_workflow(
            $pdo,
            $session['workflow_id'],
            $session['state_id'],
            $input,
            $session['context'],
            $session['snapshots']
        );

        fw_store_workflow_session(
            $pdo,
            $sessionId,

            $session['workflow_id'],

            $result['state']
            ?? $session['state_id'],

            $result['status']
            ?? 'active',

            $result['expected_input']
            ?? null,

            $result['context']
            ?? $session['context'],

            $result['snapshots']
            ?? $session['snapshots']
        );

        $result['session_id'] = $sessionId;

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Start new workflow session
    |--------------------------------------------------------------------------
    */

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

    $result = fw_start_workflow_from_chat(
        $pdo,
        $userMessage
    );

    $sessionId = fw_generate_workflow_session_id();

    fw_store_workflow_session(
        $pdo,
        $sessionId,

        $workflowId,

        $result['state']
        ?? 'unknown',

        $result['status']
        ?? 'active',

        $result['expected_input']
        ?? null,

        $result['context']
        ?? [],

        $result['snapshots']
        ?? []
    );

    $result['session_id'] = $sessionId;

    return $result;
}