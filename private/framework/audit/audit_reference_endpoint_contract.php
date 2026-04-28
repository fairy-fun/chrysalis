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

        $handlerSource = file_get_contents($expectedFile);
        if (!is_string($handlerSource)) {
            $violations[] = [
                'rule' => 'reference_handler_must_be_readable',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
            continue;
        }

        $sqlSource = $handlerSource;
        foreach (audit_reference_endpoint_required_sources($repoRoot, $handlerPath, $handlerSource) as $requiredSource) {
            $sqlSource .= "\n" . $requiredSource;
        }

        if (!str_contains($handlerSource, "'status' => 'ok'")) {
            $violations[] = [
                'rule' => 'reference_response_must_include_ok_status',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!preg_match('/[\'\"]database[\'\"]\s*=>/i', $handlerSource)) {
            $violations[] = [
                'rule' => 'reference_response_must_include_database',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!preg_match('/SELECT\s+[^;]*\b(?:[a-zA-Z0-9_]+\.)?[a-zA-Z0-9_]+\s+AS\s+id\b/i', $sqlSource)
            && !preg_match('/SELECT\s+[^;]*\bid\b/i', $sqlSource)
        ) {
            $violations[] = [
                'rule' => 'reference_select_must_include_id',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (
            !preg_match('/SELECT\s+[^;]*\b(?:[a-zA-Z0-9_]+\.)?[a-zA-Z0-9_]+\s+AS\s+label\b/i', $sqlSource)
            && !preg_match('/SELECT\s+[^;]*\blabel\b/i', $sqlSource)
            && !preg_match('/SELECT\s+[^;]*\b[a-zA-Z0-9_]+_value\b/i', $sqlSource)
        ) {
            $violations[] = [
                'rule' => 'reference_select_must_include_label_or_value',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!str_contains($handlerSource, 'REQUEST_METHOD') || !str_contains($handlerSource, "'GET'")) {
            $violations[] = [
                'rule' => 'reference_endpoint_must_be_get_only',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!str_contains($handlerSource, 'requireAuth();')) {
            $violations[] = [
                'rule' => 'reference_endpoint_must_require_auth',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        if (!str_contains($sqlSource, 'SELECT')) {
            $violations[] = [
                'rule' => 'reference_endpoint_must_select_only',
                'operation' => $operation,
                'handler' => $handlerPath,
            ];
        }

        foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER ', 'CREATE '] as $forbiddenSql) {
            if (stripos($sqlSource, $forbiddenSql) !== false) {
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

function audit_reference_endpoint_required_sources(string $repoRoot, string $handlerPath, string $handlerSource): array
{
    $sources = [];
    $handlerDirectory = dirname($repoRoot . '/' . $handlerPath);

    if (!preg_match_all('/require_once\s+__DIR__\s*\.\s*[\'\"]([^\'\"]+)[\'\"]\s*;/i', $handlerSource, $matches)) {
        return $sources;
    }

    foreach ($matches[1] as $relativePath) {
        $requiredPath = realpath($handlerDirectory . '/' . $relativePath);

        if (!is_string($requiredPath)) {
            continue;
        }

        $frameworkReferenceRoot = realpath($repoRoot . '/private/framework/reference');
        if (!is_string($frameworkReferenceRoot)) {
            continue;
        }

        if (!str_starts_with($requiredPath, $frameworkReferenceRoot . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $source = file_get_contents($requiredPath);
        if (is_string($source)) {
            $sources[] = $source;
        }
    }

    return $sources;
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
