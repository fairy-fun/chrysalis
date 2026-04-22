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
$_SERVER['CONTENT_TYPE'] = 'application/json';

$decoded = json_decode($jsonBody, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "Invalid JSON passed to runner\n");
    exit(2);
}

$GLOBALS['_API_BODY'] = $decoded;
$GLOBALS['_QUERY_BODY'] = $decoded;
$GLOBALS['__CI_RAW_REQUEST_BODY'] = $jsonBody;

function ci_get_php_input(): string
{
    return $GLOBALS['__CI_RAW_REQUEST_BODY'] ?? '';
}

ob_start();
require $scriptPath;
$output = ob_get_clean();

if (!is_string($output)) {
    fwrite(STDERR, "Runner captured no output\n");
    exit(2);
}

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
    if ($path !== '' && is_file($path) && !unlink($path)) {
        fail("Unable to delete temporary file: $path");
    }
}

function load_seeded_ids(string $repoRoot): array
{
    $path = $repoRoot . '/private/framework/ci/.seeded_ids.json';

    if (!is_file($path)) {
        fail('Missing seeded IDs file: ' . $path);
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        fail('Unable to read seeded IDs file: ' . $path);
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        fail('Seeded IDs file did not contain valid JSON: ' . $path);
    }

    foreach (['medley_id', 'medley_name', 'figure_1_id', 'figure_2_id'] as $requiredKey) {
        if (!array_key_exists($requiredKey, $json)) {
            fail('Seeded IDs file missing required key: ' . $requiredKey);
        }
    }

    if (!is_int($json['medley_id']) || $json['medley_id'] < 1) {
        fail('Seeded IDs file has invalid medley_id');
    }

    if (!is_string($json['medley_name']) || trim($json['medley_name']) === '') {
        fail('Seeded IDs file has invalid medley_name');
    }

    if (!is_int($json['figure_1_id']) || $json['figure_1_id'] < 1) {
        fail('Seeded IDs file has invalid figure_1_id');
    }

    if (!is_int($json['figure_2_id']) || $json['figure_2_id'] < 1) {
        fail('Seeded IDs file has invalid figure_2_id');
    }

    return $json;
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

    if (!is_array($json)) {
        fail(
            'Endpoint execution returned non-JSON output' .
            ' (exit code ' . $exitCode . '): ' . $raw
        );
    }

    return [
        'exit_code' => $exitCode,
        'raw' => $raw,
        'json' => $json,
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

$repoRoot = repo_root();
$runnerPath = '';

try {
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
    assert_contains_paths(
        $frameworkJson['entries'] ?? [],
        ['private/framework/contracts'],
        'listRepo private/framework'
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
            'path' => '',
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
    $indexGetFileResult = run_endpoint(
        $runnerPath,
        $indexScript,
        [
            'operation' => 'getRepoFile',
            'path' => 'public_html/pecherie/chill-api/index.php',
        ]
    );

    $indexGetFileJson = assert_ok_result($indexGetFileResult, 'index getRepoFile dispatch');

    if (!isset($indexGetFileJson['contents'])) {
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
                'operation' => 'notARealOperation',
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

    /*
     * resolveMedleyCore behaviour
     *
     * These tests rely on the fixture seeded by:
     * private/framework/ci/seed_ci_data.php
     */
    $resolveMedleyCoreScript = $repoRoot . '/public_html/pecherie/chill-api/choreography/resolve_medley_core.php';

    if (!is_file($resolveMedleyCoreScript)) {
        fail('Missing resolve_medley_core.php');
    }

    $seededIds = load_seeded_ids($repoRoot);

    $knownMedleyId = $seededIds['medley_id'];
    $knownMedleyName = $seededIds['medley_name'];
    $figure1Id = $seededIds['figure_1_id'];
    $figure2Id = $seededIds['figure_2_id'];
    $unknownMedleyName = '__definitely_missing_medley__';

    /*
     * resolveMedleyCore by medley_id
     */
    $resolveByIdResult = run_endpoint(
        $runnerPath,
        $resolveMedleyCoreScript,
        ['medley_id' => $knownMedleyId]
    );

    $resolveByIdJson = assert_ok_result($resolveByIdResult, 'resolveMedleyCore by medley_id');

    if (!is_int($resolveByIdJson['medley_id'] ?? null) || $resolveByIdJson['medley_id'] < 1) {
        fail('resolveMedleyCore by medley_id returned invalid medley_id');
    }

    if (!array_key_exists('row_count', $resolveByIdJson) || !is_int($resolveByIdJson['row_count'])) {
        fail('resolveMedleyCore by medley_id missing integer row_count');
    }

    if ($resolveByIdJson['row_count'] < 2) {
        fail('resolveMedleyCore by medley_id expected at least 2 rows');
    }

    if (!array_key_exists('rows', $resolveByIdJson) || !is_array($resolveByIdJson['rows'])) {
        fail('resolveMedleyCore by medley_id missing rows array');
    }

    $resolveByIdRows = $resolveByIdJson['rows'];

    if (!isset($resolveByIdRows[0]) || !is_array($resolveByIdRows[0])) {
        fail('resolveMedleyCore by medley_id missing first row');
    }

    if (($resolveByIdRows[0]['figure_id'] ?? null) !== $figure1Id) {
        fail(
            'resolveMedleyCore by medley_id expected first row figure_id = ' .
            $figure1Id
        );
    }

    if (($resolveByIdRows[0]['next_figure_id'] ?? null) !== $figure2Id) {
        fail(
            'resolveMedleyCore by medley_id expected first row next_figure_id = ' .
            $figure2Id
        );
    }

    if (($resolveByIdRows[0]['transition_legality_id'] ?? null) !== 'legal') {
        fail('resolveMedleyCore by medley_id expected first row transition_legality_id = legal');
    }

    ok('resolveMedleyCore by medley_id returned expected payload');

    /*
     * resolveMedleyCore by medley_name
     */
    $resolveByNameResult = run_endpoint(
        $runnerPath,
        $resolveMedleyCoreScript,
        ['medley_name' => $knownMedleyName]
    );

    $resolveByNameJson = assert_ok_result($resolveByNameResult, 'resolveMedleyCore by medley_name');

    if (($resolveByNameJson['medley_id'] ?? null) !== $knownMedleyId) {
        fail('resolveMedleyCore by medley_name returned unexpected medley_id');
    }

    if (($resolveByNameJson['requested_medley_name'] ?? null) !== $knownMedleyName) {
        fail('resolveMedleyCore by medley_name missing requested_medley_name');
    }

    if (!array_key_exists('row_count', $resolveByNameJson) || !is_int($resolveByNameJson['row_count'])) {
        fail('resolveMedleyCore by medley_name missing integer row_count');
    }

    if ($resolveByNameJson['row_count'] < 2) {
        fail('resolveMedleyCore by medley_name expected at least 2 rows');
    }

    if (!array_key_exists('rows', $resolveByNameJson) || !is_array($resolveByNameJson['rows'])) {
        fail('resolveMedleyCore by medley_name missing rows array');
    }

    $resolveByNameRows = $resolveByNameJson['rows'];

    if (!isset($resolveByNameRows[0]) || !is_array($resolveByNameRows[0])) {
        fail('resolveMedleyCore by medley_name missing first row');
    }

    if (($resolveByNameRows[0]['figure_id'] ?? null) !== $figure1Id) {
        fail(
            'resolveMedleyCore by medley_name expected first row figure_id = ' .
            $figure1Id
        );
    }

    if (($resolveByNameRows[0]['next_figure_id'] ?? null) !== $figure2Id) {
        fail(
            'resolveMedleyCore by medley_name expected first row next_figure_id = ' .
            $figure2Id
        );
    }

    if (($resolveByNameRows[0]['transition_legality_id'] ?? null) !== 'legal') {
        fail('resolveMedleyCore by medley_name expected first row transition_legality_id = legal');
    }

    ok('resolveMedleyCore by medley_name returned expected payload');

    /*
     * resolveMedleyCore rejects both medley_id and medley_name
     */
    assert_error_result(
        run_endpoint(
            $runnerPath,
            $resolveMedleyCoreScript,
            [
                'medley_id' => $knownMedleyId,
                'medley_name' => $knownMedleyName,
            ]
        ),
        'Provide exactly one of medley_id or medley_name',
        'resolveMedleyCore both inputs'
    );

    /*
     * resolveMedleyCore rejects missing identifier
     */
    assert_error_result(
        run_endpoint(
            $runnerPath,
            $resolveMedleyCoreScript,
            []
        ),
        'Missing medley_id or medley_name',
        'resolveMedleyCore missing identifier'
    );

    /*
     * resolveMedleyCore rejects unknown medley_name
     */
    assert_error_result(
        run_endpoint(
            $runnerPath,
            $resolveMedleyCoreScript,
            ['medley_name' => $unknownMedleyName]
        ),
        'Medley not found by name',
        'resolveMedleyCore unknown medley_name'
    );

    /*
     * resolveMedleyCore via index dispatch by medley_name
     */
    $indexResolveByNameResult = run_endpoint(
        $runnerPath,
        $indexScript,
        [
            'operation' => 'resolveMedleyCore',
            'medley_name' => $knownMedleyName,
        ]
    );

    $indexResolveByNameJson = assert_ok_result($indexResolveByNameResult, 'index resolveMedleyCore dispatch by medley_name');

    if (($indexResolveByNameJson['medley_id'] ?? null) !== $knownMedleyId) {
        fail('index resolveMedleyCore dispatch by medley_name returned unexpected medley_id');
    }

    if (!array_key_exists('rows', $indexResolveByNameJson) || !is_array($indexResolveByNameJson['rows'])) {
        fail('index resolveMedleyCore dispatch by medley_name missing rows array');
    }

    ok('index resolveMedleyCore dispatch by medley_name works');

    ok('Repo API behaviour tests passed');
} finally {
    delete_file_if_present($runnerPath);
}