<?php

declare(strict_types=1);

function fw_generate_workflow_session_id(): string
{
    return 'workflow_session:' . bin2hex(
            random_bytes(16)
        );
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

function fw_delete_workflow_session(
    PDO $pdo,
    string $sessionId
): void {

    $stmt = $pdo->prepare(
        '
        DELETE FROM workflow_sessions
        WHERE session_id = :session_id
        '
    );

    $stmt->execute([
        'session_id' => $sessionId,
    ]);
}