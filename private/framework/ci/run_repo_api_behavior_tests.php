<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
}

function ok(string $message): void
{
    fwrite(STDOUT, 'OK: ' . $message . PHP_EOL);
}

function repo_root(): string
{
    return dirname(__DIR__, 3);
}

function write_ci_config(string $repoRoot): void
{
    $visibilityFile = $repoRoot . '/private/framework/contracts/repo_visibility.php';

    if (!is_file($visibilityFile)) {
        fail('Missing repo_visibility.php');
    }

    $configPath = $repoRoot . '/pecherie_config.php';

    $contents = <<<'PHP'
<?php

declare(strict_types=1);

$visibility = require __DIR__ . '/private/framework/contracts/repo_visibility.php';

return [
    'pecherie_api_key' => 'ci-test-key',
    'chrysalis_repo_root' => __DIR__,
    'chrysalis_repo_visible_prefixes' => $visibility['visible_prefixes'],
    'chrysalis_repo_visible_files' => $visibility['visible_files'],
];
PHP;

    if (file_put_contents($configPath, $contents . PHP_EOL) === false) {
        fail('Unable to write CI pecherie_config.php');
    }

    ok('Wrote CI pecherie_config.php');
}

function make_runner_script(string $repoRoot): string
{
    $runnerPath = $repoRoot . '/private/framework/ci/.repo_api_request_runner.php';

    $contents = <<<'PHP'
<?php

declare(strict_types=1);

if ($argc < 4) {
    fwrite(STDERR, "Usage: php runner.php <script> <method> <apiKey> [jsonBody]\n");
    exit(2);
}

$scriptPath = $argv[1];
$method = $argv[2];
$apiKey = $argv[3];
$jsonBody = $argv[4] ?? '{}';

$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['HTTP_X_API_KEY'] = $apiKey;

$decoded = json_decode($jsonBody, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "Invalid JSON passed to runner\n");
    exit(2);
}

$GLOBALS['_API_BODY'] = $decoded;
$GLOBALS['_QUERY_BODY'] = $decoded;

ob_start();
require $scriptPath;
$output = ob_get_clean();

fwrite(STDOUT, $output);
PHP;

    if (file_put_contents($runnerPath, $contents . PHP_EOL) === false) {
        fail('Unable to write runner script');
    }

    ok('Wrote runner script');

    return $runnerPath;
}

function delete_file_if_present(string $path): void
{
    if (is_file($path) && !unlink($path)) {
        fail("Unable to delete temporary file: $path");
    }
}

function run_endpoint(
    string $runner,
    string $targetScript,
    array $body,
    string $apiKey = 'ci-test-key',
    string $method = 'POST'
): array {
    $command = sprintf(
        'php %s %s %s %s %s',
        escapeshellarg($runner),
        escapeshellarg($targetScript),
        escapeshellarg($method),
        escapeshellarg($apiKey),
        escapeshellarg(json_encode($body, JSON_UNESCAPED_SLASHES))
    );

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    $raw = implode("\n", $output);
    $json = json_decode($raw, true);

    return [
        'exit_code' => $exitCode,
        'raw' => $raw,
        'json' => is_array($json) ? $json : null,
    ];
}

function assert_json_result(array $result, string $label): array
{
    if (!is_array($result['json'])) {
        fail($label . ' returned non-JSON output: ' . $result['raw']);
    }

    return $result['json'];
}

function assert_ok_result(array $result, string $label): array
{
    $json = assert_json_result($result, $label);

    if (($json['status'] ?? null) !== 'ok') {
        fail(
            $label . " expected status 'ok', got " .
            var_export($json['status'] ?? null, true) .
            ' raw=' . $result['raw']
        );
    }

    ok($label . ' returned ok');

    return $json;
}

function assert_error_result(array $result, string $expectedError, string $label): array
{
    $json = assert_json_result($result, $label);

    if (($json['status'] ?? null) !== 'error') {
        fail(
            $label . " expected status 'error', got " .
            var_export($json['status'] ?? null, true) .
            ' raw=' . $result['raw']
        );
    }

    if (($json['error'] ?? null) !== $expectedError) {
        fail(
            $label . " expected error '$expectedError', got " .
            var_export($json['error'] ?? null, true) .
            ' raw=' . $result['raw']
        );
    }

    ok($label . ' returned expected error: ' . $expectedError);

    return $json;
}

function assert_contains_paths(array $entries, array $expectedPaths, string $label): void
{
    $actualPaths = array_column($entries, 'path');

    foreach ($expectedPaths as $expectedPath) {
        if (!in_array($expectedPath, $actualPaths, true)) {
            fail($label . ' missing expected path: ' . $expectedPath);
        }
    }

    ok($label . ' contains expected paths');
}

function assert_not_contains_names(array $entries, array $forbiddenNames, string $label): void
{
    $actualNames = array_column($entries, 'name');

    foreach ($forbiddenNames as $forbiddenName) {
        if (in_array($forbiddenName, $actualNames, true)) {
            fail($label . ' leaked forbidden name: ' . $forbiddenName);
        }
    }

    ok($label . ' did not leak forbidden names');
}

function assert_not_contains_paths(array $entries, array $forbiddenPaths, string $label): void
{
    $actualPaths = array_column($entries, 'path');

    foreach ($forbiddenPaths as $forbiddenPath) {
        if (in_array($forbiddenPath, $actualPaths, true)) {
            fail($label . ' leaked forbidden path: ' . $forbiddenPath);
        }
    }

    ok($label . ' did not leak forbidden paths');
}

$repoRoot = repo_root();
$runnerPath = '';
$configPath = $repoRoot . '/pecherie_config.php';

try {
    write_ci_config($repoRoot);
    $runnerPath = make_runner_script($repoRoot);

    $listRepoScript = $repoRoot . '/public_html/pecherie/chill-api/repo/list_repo.php';
    $getRepoFileScript = $repoRoot . '/public_html/pecherie/chill-api/repo/get_repo_file.php';

    if (!is_file($listRepoScript)) {
        fail('Missing list_repo.php');
    }

    if (!is_file($getRepoFileScript)) {
        fail('Missing get_repo_file.php');
    }

    /*
     * listRepo: root traversal ancestors must be visible.
     */
    $rootResult = run_endpoint($runnerPath, $listRepoScript, ['path' => '']);
    $rootJson = assert_ok_result($rootResult, 'listRepo root');

    $rootEntries = $rootJson['entries'] ?? [];
    if (!is_array($rootEntries)) {
        fail('listRepo root entries missing or invalid');
    }

    assert_contains_paths(
        $rootEntries,
        ['.github', 'private', 'public_html'],
        'listRepo root'
    );

    assert_not_contains_names(
        $rootEntries,
        ['.git', '.idea', 'node_modules', 'vendor'],
        'listRepo root'
    );

    /*
     * listRepo: follow-up traversal chain must hold.
     */
    $githubResult = run_endpoint($runnerPath, $listRepoScript, ['path' => '.github']);
    $githubJson = assert_ok_result($githubResult, 'listRepo .github');
    assert_contains_paths(
        $githubJson['entries'] ?? [],
        ['.github/workflows'],
        'listRepo .github'
    );

    $privateResult = run_endpoint($runnerPath, $listRepoScript, ['path' => 'private']);
    $privateJson = assert_ok_result($privateResult, 'listRepo private');
    assert_contains_paths(
        $privateJson['entries'] ?? [],
        ['private/framework'],
        'listRepo private'
    );

    $frameworkResult = run_endpoint($runnerPath, $listRepoScript, ['path' => 'private/framework']);
    $frameworkJson = assert_ok_result($frameworkResult, 'listRepo private/framework');
    $frameworkEntries = $frameworkJson['entries'] ?? [];
    if (!is_array($frameworkEntries)) {
        fail('listRepo private/framework entries missing or invalid');
    }

    assert_contains_paths(
        $frameworkEntries,
        [
            'private/framework/contracts',
            'private/framework/procedures',
            'private/framework/directives',
        ],
        'listRepo private/framework'
    );

    assert_not_contains_names(
        $frameworkEntries,
        ['bootstrap.php'],
        'listRepo private/framework'
    );

    /*
     * listRepo: mixed-directory visibility must include only approved siblings.
     */
    $proceduresResult = run_endpoint(
        $runnerPath,
        $listRepoScript,
        ['path' => 'private/framework/procedures']
    );
    $proceduresJson = assert_ok_result($proceduresResult, 'listRepo private/framework/procedures');
    $procedureEntries = $proceduresJson['entries'] ?? [];
    if (!is_array($procedureEntries)) {
        fail('listRepo private/framework/procedures entries missing or invalid');
    }

    assert_contains_paths(
        $procedureEntries,
        [
            'private/framework/procedures/procedure_registry_reader.php',
            'private/framework/procedures/procedure_source_inspector.php',
        ],
        'listRepo private/framework/procedures'
    );

    assert_not_contains_names(
        $procedureEntries,
        ['procedure_registration_service.php'],
        'listRepo private/framework/procedures'
    );

    assert_not_contains_paths(
        $procedureEntries,
        ['private/framework/procedures/procedure_registration_service.php'],
        'listRepo private/framework/procedures'
    );

    $directivesResult = run_endpoint(
        $runnerPath,
        $listRepoScript,
        ['path' => 'private/framework/directives']
    );
    $directivesJson = assert_ok_result($directivesResult, 'listRepo private/framework/directives');
    $directiveEntries = $directivesJson['entries'] ?? [];
    if (!is_array($directiveEntries)) {
        fail('listRepo private/framework/directives entries missing or invalid');
    }

    assert_contains_paths(
        $directiveEntries,
        [
            'private/framework/directives/directive_text.php',
            'private/framework/directives/directive_validator.php',
        ],
        'listRepo private/framework/directives'
    );

    assert_not_contains_names(
        $directiveEntries,
        ['directive_service.php'],
        'listRepo private/framework/directives'
    );

    assert_not_contains_paths(
        $directiveEntries,
        ['private/framework/directives/directive_service.php'],
        'listRepo private/framework/directives'
    );

    /*
     * listRepo: denied paths.
     */
    assert_error_result(
        run_endpoint($runnerPath, $listRepoScript, ['path' => '.git']),
        'Path is not visible',
        'listRepo hidden directory'
    );

    assert_error_result(
        run_endpoint($runnerPath, $listRepoScript, ['path' => '../']),
        'Path traversal is not allowed',
        'listRepo traversal'
    );

    assert_error_result(
        run_endpoint($runnerPath, $listRepoScript, ['path' => 'public_html/pecherie/chill-api']),
        'Path is not visible',
        'listRepo non-visible directory'
    );

    /*
     * getRepoFile: explicitly visible file.
     */
    $indexFileResult = run_endpoint(
        $runnerPath,
        $getRepoFileScript,
        ['path' => 'public_html/pecherie/chill-api/index.php']
    );
    $indexFileJson = assert_ok_result($indexFileResult, 'getRepoFile explicit visible file');

    if (($indexFileJson['path'] ?? null) !== 'public_html/pecherie/chill-api/index.php') {
        fail('getRepoFile explicit visible file returned unexpected path');
    }

    if (!array_key_exists('contents', $indexFileJson)) {
        fail('getRepoFile explicit visible file missing contents');
    }

    ok('getRepoFile explicit visible file returned expected payload');

    /*
     * getRepoFile: file under visible prefix.
     */
    $prefixFileResult = run_endpoint(
        $runnerPath,
        $getRepoFileScript,
        ['path' => 'private/framework/contracts/repo_visibility.php']
    );
    $prefixFileJson = assert_ok_result($prefixFileResult, 'getRepoFile visible prefix file');

    if (($prefixFileJson['path'] ?? null) !== 'private/framework/contracts/repo_visibility.php') {
        fail('getRepoFile visible prefix file returned unexpected path');
    }

    ok('getRepoFile visible prefix file returned expected payload');

    /*
     * getRepoFile: denied paths.
     */
    assert_error_result(
        run_endpoint($runnerPath, $getRepoFileScript, ['path' => 'pecherie_config.php']),
        'Path is not visible',
        'getRepoFile hidden config'
    );

    assert_error_result(
        run_endpoint($runnerPath, $getRepoFileScript, ['path' => 'private/framework/bootstrap.php']),
        'Path is not visible',
        'getRepoFile hidden bootstrap'
    );

    assert_error_result(
        run_endpoint($runnerPath, $getRepoFileScript, ['path' => 'private/framework/db/framework_db_calls.php']),
        'Path is not visible',
        'getRepoFile hidden db adapter'
    );

    assert_error_result(
        run_endpoint($runnerPath, $getRepoFileScript, ['path' => 'private/framework/procedures/procedure_registration_service.php']),
        'Path is not visible',
        'getRepoFile hidden procedure service'
    );

    assert_error_result(
        run_endpoint($runnerPath, $getRepoFileScript, ['path' => 'private/framework/directives/directive_service.php']),
        'Path is not visible',
        'getRepoFile hidden directive service'
    );

    assert_error_result(
        run_endpoint($runnerPath, $getRepoFileScript, ['path' => '../pecherie_config.php']),
        'Path traversal is not allowed',
        'getRepoFile traversal'
    );

    assert_error_result(
        run_endpoint($runnerPath, $getRepoFileScript, ['path' => 'private']),
        'File not found',
        'getRepoFile directory path'
    );

    assert_error_result(
        run_endpoint($runnerPath, $getRepoFileScript, ['path' => 'public_html/pecherie/chill-api/repo']),
        'File not found',
        'getRepoFile visible directory as file'
    );

    /*
     * Auth and method enforcement.
     */
    assert_error_result(
        run_endpoint($runnerPath, $listRepoScript, ['path' => ''], 'wrong-key'),
        'Unauthorized',
        'listRepo unauthorized'
    );

    assert_error_result(
        run_endpoint(
            $runnerPath,
            $getRepoFileScript,
            ['path' => 'public_html/pecherie/chill-api/index.php'],
            'ci-test-key',
            'GET'
        ),
        'Method not allowed',
        'getRepoFile wrong method'
    );

    /*
     * index.php dispatch behaviour
     */
    $indexScript = $repoRoot . '/public_html/pecherie/chill-api/index.php';

    if (!is_file($indexScript)) {
        fail('Missing index.php');
    }

    /*
     * listRepo via index
     */
    $indexListResult = run_endpoint(
        $runnerPath,
        $indexScript,
        [
            'operation' => 'listRepo',
            'path' => ''
        ]
    );

    $indexListJson = assert_ok_result($indexListResult, 'index listRepo dispatch');

    if (!isset($indexListJson['entries'])) {
        fail('index listRepo did not return entries');
    }

    ok('index listRepo dispatch works');

    /*
     * getRepoFile via index
     */
    $indexFileResult = run_endpoint(
        $runnerPath,
        $indexScript,
        [
            'operation' => 'getRepoFile',
            'path' => 'public_html/pecherie/chill-api/index.php'
        ]
    );

    $indexFileJson = assert_ok_result($indexFileResult, 'index getRepoFile dispatch');

    if (!isset($indexFileJson['contents'])) {
        fail('index getRepoFile missing contents');
    }

    ok('index getRepoFile dispatch works');

    /*
     * unknown operation must fail
     */
    assert_error_result(
        run_endpoint(
            $runnerPath,
            $indexScript,
            [
                'operation' => 'notARealOperation'
            ]
        ),
        'Unknown operation: notARealOperation',
        'index unknown operation'
    );

    /*
     * missing operation must fail
     */
    assert_error_result(
        run_endpoint(
            $runnerPath,
            $indexScript,
            []
        ),
        'Missing operation',
        'index missing operation'
    );

    ok('Repo API behaviour tests passed');
} finally {
    delete_file_if_present($runnerPath);
    delete_file_if_present($configPath);
}
