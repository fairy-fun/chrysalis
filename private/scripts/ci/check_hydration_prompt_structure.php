<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
require_once dirname(__DIR__, 3) . '/private/framework/contracts/repo_contract.php';

function hydration_prompt_fail(string $message, array $extra = []): void
{
    http_response_code(500);

    $payload = array_merge([
        'status' => 'error',
        'message' => $message,
    ], $extra);

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

$repoRoot = dirname(__DIR__, 3);
$expectedRelativePath = 'private/framework/contracts/chrysalis_hydration_prompt.md';

if (!defined('FW_AUDIT_ENTRYPOINT')) {
    hydration_prompt_fail('FW_AUDIT_ENTRYPOINT is not defined in repo_contract.php');
}

if (FW_AUDIT_ENTRYPOINT !== $expectedRelativePath) {
    hydration_prompt_fail('FW_AUDIT_ENTRYPOINT does not match expected hydration prompt path', [
        'expected_path' => $expectedRelativePath,
        'declared_path' => FW_AUDIT_ENTRYPOINT,
    ]);
}

if (defined('FW_REPO_CONTRACT')) {
    if (
        !is_array(FW_REPO_CONTRACT)
        || !array_key_exists('audit_entrypoint', FW_REPO_CONTRACT)
        || FW_REPO_CONTRACT['audit_entrypoint'] !== FW_AUDIT_ENTRYPOINT
    ) {
        hydration_prompt_fail('FW_REPO_CONTRACT audit_entrypoint does not match FW_AUDIT_ENTRYPOINT', [
            'contract_value' => is_array(FW_REPO_CONTRACT) && array_key_exists('audit_entrypoint', FW_REPO_CONTRACT)
                ? FW_REPO_CONTRACT['audit_entrypoint']
                : null,
            'constant_value' => FW_AUDIT_ENTRYPOINT,
        ]);
    }
}

$relativePath = FW_AUDIT_ENTRYPOINT;
$normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
$fullPath = $repoRoot . DIRECTORY_SEPARATOR . $normalizedPath;

if (!is_file($fullPath)) {
    hydration_prompt_fail('Hydration prompt file is missing', [
        'expected_path' => $relativePath,
        'resolved_path' => $fullPath,
    ]);
}

$content = file_get_contents($fullPath);
if ($content === false) {
    hydration_prompt_fail('Unable to read hydration prompt file', [
        'expected_path' => $relativePath,
        'resolved_path' => $fullPath,
    ]);
}

$requiredSections = [
    '# Chrysalis Hydration Prompt',
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
    hydration_prompt_fail('Hydration prompt is missing required sections', [
        'expected_path' => $relativePath,
        'resolved_path' => $fullPath,
        'missing_sections' => $missingSections,
    ]);
}

if (strpos($content, '<!-- CHRYSALIS_PROMPT_VERSION:') === false) {
    hydration_prompt_fail('Hydration prompt is missing version marker', [
        'expected_path' => $relativePath,
        'resolved_path' => $fullPath,
    ]);
}

echo json_encode([
    'status' => 'ok',
    'message' => 'Hydration prompt structure is valid',
    'checked_path' => $relativePath,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;