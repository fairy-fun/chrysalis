<?php

declare(strict_types=1);

function audit_reference_endpoint_contract(PDO $pdo, string $schemaName): array
{
    unset($pdo, $schemaName);

    $repoRoot = dirname(__DIR__, 3);
    $contract = require $repoRoot . '/private/framework/contracts/repo_visibility.php';

    $violations = [];

    foreach (($contract['required_operations'] ?? []) as $operation => $handlerPath) {
        if (!str_starts_with($handlerPath, 'public_html/pecherie/chill-api/reference/')) {
            continue;
        }

        $expectedFile = $repoRoot . '/' . $handlerPath;

        if (!str_starts_with($operation, 'list')) {
            $violations[] = [
                'rule' => 'reference_operation_must_start_with_list',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!file_exists($expectedFile)) {
            $violations[] = [
                'rule' => 'reference_handler_must_exist',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
            continue;
        }

        $source = file_get_contents($expectedFile);

        if (!str_contains($source, "'status' => 'ok'")) {
            $violations[] = [
                'rule' => 'reference_response_must_include_ok_status',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!str_contains($source, "'database' => \$expectedDatabase")) {
            $violations[] = [
                'rule' => 'reference_response_must_include_database',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!preg_match('/SELECT\s+[^;]*\bid\b/i', $source)) {
            $violations[] = [
                'rule' => 'reference_select_must_include_id',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (
            !preg_match('/SELECT\s+[^;]*\blabel\b/i', $source)
            && !preg_match('/SELECT\s+[^;]*\b[a-zA-Z0-9_]+_value\b/i', $source)
        ) {
            $violations[] = [
                'rule' => 'reference_select_must_include_label_or_value',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!str_contains($source, "REQUEST_METHOD") || !str_contains($source, "'GET'")) {
            $violations[] = [
                'rule' => 'reference_endpoint_must_be_get_only',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!str_contains($source, 'requireAuth();')) {
            $violations[] = [
                'rule' => 'reference_endpoint_must_require_auth',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!str_contains($source, 'SELECT')) {
            $violations[] = [
                'rule' => 'reference_endpoint_must_select_only',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER ', 'CREATE '] as $forbiddenSql) {
            if (stripos($source, $forbiddenSql) !== false) {
                $violations[] = [
                    'rule' => 'reference_endpoint_must_not_mutate',
                    'operation' => $operation,
                    'handler' => $handlerPath,
                    'forbidden_sql' => trim($forbiddenSql),
                ];
            }
        }
    }

    return [
        'ok' => count($violations) === 0,
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function assert_reference_endpoint_contract(PDO $pdo, string $schemaName): void
{
    $result = audit_reference_endpoint_contract($pdo, $schemaName);

    if (!$result['ok']) {
        throw new RuntimeException(
            'Reference endpoint contract audit failed: '
            . json_encode($result, JSON_UNESCAPED_SLASHES)
        );
    }
}