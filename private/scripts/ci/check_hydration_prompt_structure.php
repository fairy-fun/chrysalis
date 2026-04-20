<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
require_once dirname(__DIR__, 3) . '/private/framework/contracts/repo_contract.php';

function fail(string $message, array $extra = []): void
{
    http_response_code(500);

    $payload = array_merge([
        'status' => 'error',
        'message' => $message,
    ], $extra);

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit(1);
}

if (!defined('FW_AUDIT_ENTRYPOINT')) {
    fail('FW_AUDIT_ENTRYPOINT is not defined in repo_contract.php');
}

$repoRoot = dirname(__DIR__, 3);
$relativePath = FW_AUDIT_ENTRYPOINT;
$fullPath = $repoRoot . '/' . $relativePath;

if (!is_file($fullPath)) {
    fail('Hydration prompt file is missing', [
        'expected_path' => $relativePath,
        'resolved_path' => $fullPath,
    ]);
}

$content = file_get_contents($fullPath);
if ($content === false) {
    fail('Unable to read hydration prompt file', [
        'expected_path' => $relativePath,
        'resolved_path' => $fullPath,
    ]);
}

$requiredSections = [
    '## 1. Identify architecture layers',
    '## 2. Trace protected call paths',
    '## 3. Enforce boundary rules',
    '## 4. Contract verification',
    '## 5. CI enforcement coverage',
    '## 6. Drift and risk analysis',
    '## 7. Actionable fixes',
    '## 8. Output format',
    '## Assumptions',
];

$missingSections = [];

foreach ($requiredSections as $section) {
    if (strpos($content, $section) === false) {
        $missingSections[] = $section;
    }
}

if ($missingSections !== []) {
    fail('Hydration prompt is missing required sections', [
        'expected_path' => $relativePath,
        'resolved_path' => $fullPath,
        'missing_sections' => $missingSections,
    ]);
}

echo json_encode([
    'status' => 'ok',
    'message' => 'Hydration prompt structure is valid',
    'checked_path' => $relativePath,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;