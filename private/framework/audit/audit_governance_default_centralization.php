<?php

declare(strict_types=1);

function audit_governance_default_centralization(): array
{
    $root = realpath(__DIR__ . '/../../');

    if ($root === false) {
        throw new RuntimeException('Unable to resolve framework root');
    }

    $allowedFiles = array_filter([
        realpath($root . '/facts/fact_governance.php'),
        realpath($root . '/classval/governance_codes.php'),
        realpath($root . '/classval/governance_domains.php'),
        realpath($root . '/classval/governance_classvals.php'),
        realpath($root . '/audit/audit_governance_default_centralization.php'),
    ], 'is_string');

    $allowedDirs = array_filter([
        realpath($root . '/audit'),
        realpath($root . '/ci'),
    ], 'is_string');

    $forbiddenNeedles = [
        'manual_author_entry',
        'implicitly_retained',
        'UNASSESSED',
        'epistemic_origin_classvals',
        'adjudication_status_classvals',
        'contradiction_state_classvals',
    ];

    $governanceFieldNeedles = [
        'epistemic_origin_classval_id',
        'adjudication_status_classval_id',
        'contradiction_state_classval_id',
    ];

    $writePathPattern = '/\b(INSERT|UPDATE|REPLACE|UPSERT|apply_|write_|create_|insert_|update_|set[A-Z_])/i';
    $uuidPattern = '/[\'"][0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}[\'"]/';

    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getRealPath();

        if (!is_string($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
            continue;
        }

        if (in_array($path, $allowedFiles, true)) {
            continue;
        }

        $relativePath = audit_governance_default_centralization_relative_path($root, $path);
        $isAllowedDir = audit_governance_default_centralization_is_under_allowed_dir($path, $allowedDirs);

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            continue;
        }

        foreach ($forbiddenNeedles as $needle) {
            if (str_contains($contents, $needle) && !$isAllowedDir) {
                $violations[] = [
                    'violation_type' => 'governance_semantic_literal_outside_authority',
                    'file' => $relativePath,
                    'needle' => $needle,
                ];
            }
        }

        $isWritePath = preg_match($writePathPattern, $contents) === 1;

        foreach ($governanceFieldNeedles as $field) {
            if (
                $isWritePath
                && str_contains($contents, $field)
                && !str_contains($contents, 'default_fact_governance(')
                && !str_contains($contents, 'resolve_fact_governance(')
            ) {
                $violations[] = [
                    'violation_type' => 'governance_write_path_without_governance_resolver',
                    'file' => $relativePath,
                    'field' => $field,
                ];
            }
        }

        $lines = preg_split('/\R/', $contents);

        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $index => $line) {
            if (preg_match($uuidPattern, $line, $uuidMatches) !== 1) {
                continue;
            }

            $window = audit_governance_default_centralization_line_window($lines, $index, 2);

            foreach ($governanceFieldNeedles as $field) {
                if (str_contains($window, $field)) {
                    $violations[] = [
                        'violation_type' => 'possible_hardcoded_governance_uuid',
                        'file' => $relativePath,
                        'line' => $index + 1,
                        'field' => $field,
                        'uuid_literal' => trim($uuidMatches[0], '\'"'),
                    ];
                }
            }
        }
    }

    return [
        'ok' => count($violations) === 0,
        'violation_count' => count($violations),
        'violations' => $violations,
    ];
}

function assert_governance_default_centralization(): void
{
    $audit = audit_governance_default_centralization();

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Governance default centralization failed: '
        . json_encode($audit['violations'], JSON_UNESCAPED_SLASHES)
    );
}

function audit_governance_default_centralization_relative_path(
    string $root,
    string $path
): string {
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (str_starts_with($path, $prefix)) {
        return substr($path, strlen($prefix));
    }

    return $path;
}

function audit_governance_default_centralization_is_under_allowed_dir(
    string $path,
    array $allowedDirs
): bool {
    foreach ($allowedDirs as $dir) {
        $prefix = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($path === $dir || str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
}

function audit_governance_default_centralization_line_window(
    array $lines,
    int $index,
    int $radius
): string {
    $start = max(0, $index - $radius);
    $end = min(count($lines) - 1, $index + $radius);

    return implode("\n", array_slice($lines, $start, $end - $start + 1));
}