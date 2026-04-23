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
    fwrite(
        STDERR,
        "Usage: php runner.php <script> <method> <apiKey> [jsonBody] [jsonQuery]\n"
    );
    exit(2);
}

$scriptPath = $argv[1];
$method = $argv[2];
$apiKey = $argv[3];
$jsonBody = $argv[4] ?? '{}';
$jsonQuery = $argv[5] ?? '{}';

$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['HTTP_X_API_KEY'] = $apiKey;
$_SERVER['CONTENT_TYPE'] = 'application/json';

$decodedBody = json_decode($jsonBody, true);
if (!is_array($decodedBody)) {
    fwrite(STDERR, "Invalid JSON body passed to runner\n");
    exit(2);
}

$decodedQuery = json_decode($jsonQuery, true);
if (!is_array($decodedQuery)) {
    fwrite(STDERR, "Invalid JSON query passed to runner\n");
    exit(2);
}

$_GET = $decodedQuery;
$_POST = [];
$_REQUEST = array_merge($_GET, $decodedBody);

$GLOBALS['_API_BODY'] = $decodedBody;
$GLOBALS['_QUERY_BODY'] = $decodedBody;
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

    ok('repoRoot=' . $repoRoot);
    ok('seededIdsPath=' . $path);
    ok('seeded_ids_exists_before_load=' . (is_file($path) ? 'yes' : 'no'));

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

    foreach (
        [
            'medley_id',
            'medley_name',
            'figure_1_id',
            'figure_2_id',
            'expression_test_character_id',
            'expression_test_domain_match_id',
            'expression_expected_default',
            'expression_expected_domain_filtered',
            'entity_test_subject_entity_id',
            'entity_test_existing_target_label',
            'entity_test_existing_target_type_id',
            'entity_test_fact_type_id',
            'entity_test_duplicate_link_target_label',
            'entity_test_duplicate_link_target_type_id',
            'entity_test_ambiguous_target_label',
            'entity_test_ambiguous_target_type_id',
            'entity_test_same_type_ambiguous_label',
            'entity_test_same_type_ambiguous_type_id',
        ] as $requiredKey
    ) {
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

    if (
        !is_string($json['expression_test_character_id']) ||
        trim($json['expression_test_character_id']) === ''
    ) {
        fail('Seeded IDs file has invalid expression_test_character_id');
    }

    if (
        !is_int($json['expression_test_domain_match_id']) ||
        $json['expression_test_domain_match_id'] < 1
    ) {
        fail('Seeded IDs file has invalid expression_test_domain_match_id');
    }

    if (!is_array($json['expression_expected_default'])) {
        fail('Seeded IDs file has invalid expression_expected_default');
    }

    if (!is_array($json['expression_expected_domain_filtered'])) {
        fail('Seeded IDs file has invalid expression_expected_domain_filtered');
    }
    if (
        !is_string($json['entity_test_subject_entity_id']) ||
        trim($json['entity_test_subject_entity_id']) === ''
    ) {
        fail('Seeded IDs file has invalid entity_test_subject_entity_id');
    }

    foreach ([
                 'entity_test_existing_target_label',
                 'entity_test_existing_target_type_id',
                 'entity_test_fact_type_id',
                 'entity_test_duplicate_link_target_label',
                 'entity_test_duplicate_link_target_type_id',
                 'entity_test_ambiguous_target_label',
                 'entity_test_ambiguous_target_type_id',
             ] as $requiredStringKey) {
        if (
            !is_string($json[$requiredStringKey]) ||
            trim($json[$requiredStringKey]) === ''
        ) {
            fail('Seeded IDs file has invalid ' . $requiredStringKey);
        }
    }

    return $json;
}

function run_endpoint(
    string $runner,
    string $targetScript,
    array $body,
    string $apiKey = 'ci-test-key',
    string $method = 'POST',
    array $query = []
): array {
    $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES);
    if ($encodedBody === false) {
        fail('Unable to encode request body JSON');
    }

    $encodedQuery = json_encode($query, JSON_UNESCAPED_SLASHES);
    if ($encodedQuery === false) {
        fail('Unable to encode request query JSON');
    }

    $command = sprintf(
        'php %s %s %s %s %s %s',
        escapeshellarg($runner),
        escapeshellarg($targetScript),
        escapeshellarg($method),
        escapeshellarg($apiKey),
        escapeshellarg($encodedBody),
        escapeshellarg($encodedQuery)
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

function run_get_endpoint(
    string $runner,
    string $targetScript,
    array $query,
    string $apiKey = 'ci-test-key'
): array {
    return run_endpoint(
        $runner,
        $targetScript,
        [],
        $apiKey,
        'GET',
        $query
    );
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

function assert_expression_success_result(array $result, string $label): array
{
    $json = assert_json_result($result, $label);

    if (($json['ok'] ?? null) !== true) {
        fail(
            $label . ' expected ok=true, got ' .
            var_export($json['ok'] ?? null, true) .
            ' raw=' . $result['raw']
        );
    }

    if (!isset($json['data']) || !is_array($json['data'])) {
        fail($label . ' missing data object');
    }

    ok($label . ' returned ok=true');

    return $json;
}

function assert_expression_error_result(
    array $result,
    string $expectedError,
    string $label
): array {
    $json = assert_json_result($result, $label);

    if (($json['ok'] ?? null) !== false) {
        fail(
            $label . ' expected ok=false, got ' .
            var_export($json['ok'] ?? null, true) .
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

function normalise_expression_output(array $resolvedOutput): array
{
    $normalised = [];

    foreach (['layer_voice', 'layer_psych', 'layer_limbic'] as $layerKey) {
        if (!array_key_exists($layerKey, $resolvedOutput) || !is_array($resolvedOutput[$layerKey])) {
            fail('Resolved output missing layer: ' . $layerKey);
        }

        $rows = [];

        foreach ($resolvedOutput[$layerKey] as $row) {
            if (!is_array($row)) {
                fail('Resolved output row was not an array');
            }

            $attributeTypeId = $row['attribute_type_id'] ?? null;
            if (!is_string($attributeTypeId) || $attributeTypeId === '') {
                fail('Resolved output row missing attribute_type_id');
            }

            $profileId = $row['profile_id'] ?? null;
            if (!is_int($profileId) || $profileId < 1) {
                fail('Resolved output row missing valid profile_id');
            }

            $valueText = $row['value_text'] ?? null;
            if (!is_string($valueText) && $valueText !== null) {
                fail('Resolved output row has invalid value_text');
            }

            $valueClassvalId = $row['value_classval_id'] ?? null;
            if (!is_string($valueClassvalId) && $valueClassvalId !== null) {
                fail('Resolved output row has invalid value_classval_id');
            }

            $rows[$attributeTypeId] = [
                'profile_id' => $profileId,
                'value_text' => $valueText,
                'value_classval_id' => $valueClassvalId,
            ];
        }

        ksort($rows);
        $normalised[$layerKey] = $rows;
    }

    return $normalised;
}

function assert_expression_output_matches(
    array $resolvedOutput,
    array $expected,
    string $label
): void {
    $actual = normalise_expression_output($resolvedOutput);
    $expectedNormalised = normalise_expression_output($expected);

    if ($actual !== $expectedNormalised) {
        fail(
            $label . ' mismatch expected=' .
            json_encode($expectedNormalised, JSON_UNESCAPED_SLASHES) .
            ' actual=' .
            json_encode($actual, JSON_UNESCAPED_SLASHES)
        );
    }

    ok($label . ' matched expected resolved_output');
}

function assert_entity_success_result(array $result, string $label): array
{
    $json = assert_json_result($result, $label);

    if (($json['ok'] ?? null) !== true) {
        fail(
            $label . ' expected ok=true, got ' .
            var_export($json['ok'] ?? null, true) .
            ' raw=' . $result['raw']
        );
    }

    if (!isset($json['data']) || !is_array($json['data'])) {
        fail($label . ' missing data object');
    }

    ok($label . ' returned ok=true');

    return $json;
}

function assert_entity_error_result(
    array $result,
    string $expectedError,
    string $label
): array {
    $json = assert_json_result($result, $label);

    if (($json['ok'] ?? null) !== false) {
        fail(
            $label . ' expected ok=false, got ' .
            var_export($json['ok'] ?? null, true) .
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

function extract_entity_steps(array $entityJson, string $label): array
{
    $data = $entityJson['data'] ?? null;

    if (!is_array($data)) {
        fail($label . ' missing data array');
    }

    $steps = $data['steps'] ?? null;

    if (!is_array($steps)) {
        fail($label . ' missing steps array');
    }

    return $steps;
}

function extract_entity_step_map(array $entityJson, string $label): array
{
    $steps = extract_entity_steps($entityJson, $label);
    $map = [];

    foreach ($steps as $step) {
        if (!is_array($step)) {
            fail($label . ' contained non-array step');
        }

        $stepName = $step['step'] ?? null;
        $sql = $step['sql'] ?? null;

        if (!is_string($stepName) || $stepName === '') {
            fail($label . ' contained step with invalid step name');
        }

        if (!is_string($sql) || trim($sql) === '') {
            fail($label . ' contained step with missing sql');
        }

        $map[$stepName] = $sql;
    }

    return $map;
}

function assert_entity_action(array $entityJson, string $expectedAction, string $label): array
{
    $data = $entityJson['data'] ?? null;

    if (!is_array($data)) {
        fail($label . ' missing data array');
    }

    if (($data['action'] ?? null) !== $expectedAction) {
        fail(
            $label . " expected action '$expectedAction', got " .
            var_export($data['action'] ?? null, true)
        );
    }

    ok($label . ' returned expected action: ' . $expectedAction);

    return $data;
}

function assert_step_names(array $stepMap, array $expectedStepNames, string $label): void
{
    $actualStepNames = array_keys($stepMap);

    if ($actualStepNames !== $expectedStepNames) {
        fail(
            $label . ' expected step names ' .
            json_encode($expectedStepNames, JSON_UNESCAPED_SLASHES) .
            ' got ' .
            json_encode($actualStepNames, JSON_UNESCAPED_SLASHES)
        );
    }

    ok($label . ' returned expected step names');
}

function assert_sql_contains_fragment(string $sql, string $fragment, string $label): void
{
    if (strpos($sql, $fragment) === false) {
        fail($label . ' missing SQL fragment: ' . $fragment . ' sql=' . $sql);
    }
}

function assert_no_entity_sql_steps(array $entityJson, string $label): void
{
    $data = $entityJson['data'] ?? null;

    if (!is_array($data)) {
        return;
    }

    if (array_key_exists('steps', $data)) {
        $steps = $data['steps'];

        if (is_array($steps) && count($steps) > 0) {
            fail($label . ' unexpectedly returned actionable SQL steps');
        }
    }

    ok($label . ' returned no actionable SQL steps');
}

function normalise_sql_string(string $sql): string
{
    $sql = trim($sql);
    $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

    return strtolower($sql);
}


$repoRoot = repo_root();
$runnerPath = '';

try {
    $runnerPath = make_runner_script($repoRoot);

    /*
 * Graph invariants must pass before any API behaviour tests.
 */
    $graphInvariantScript = $repoRoot . '/private/framework/ci/run_graph_invariants.php';

    if (!is_file($graphInvariantScript)) {
        fail('Missing run_graph_invariants.php');
    }

    $command = sprintf('php %s', escapeshellarg($graphInvariantScript));
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    $raw = implode("\n", $output);

    if ($exitCode !== 0) {
        fail('Graph invariants failed: ' . $raw);
    }

    ok('Graph invariants passed');

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

    /*
     * resolveExpressionOutput behaviour
     *
     * These tests rely on the fixture seeded by:
     * private/framework/ci/seed_ci_data.php
     */
    $resolveExpressionOutputScript =
        $repoRoot . '/public_html/pecherie/chill-api/character/resolve_expression_output.php';

    if (!is_file($resolveExpressionOutputScript)) {
        fail('Missing character/resolve_expression_output.php');
    }

    $expressionTestCharacterId = $seededIds['expression_test_character_id'];
    $expressionDomainMatchId = $seededIds['expression_test_domain_match_id'];
    $expressionExpectedDefault = $seededIds['expression_expected_default'];
    $expressionExpectedDomainFiltered = $seededIds['expression_expected_domain_filtered'];

    /*
     * resolveExpressionOutput success path
     */
    $expressionSuccessResult = run_get_endpoint(
        $runnerPath,
        $resolveExpressionOutputScript,
        [
            'character_id' => $expressionTestCharacterId,
        ]
    );

    $expressionSuccessJson = assert_expression_success_result(
        $expressionSuccessResult,
        'resolveExpressionOutput success'
    );

    $expressionData = $expressionSuccessJson['data'];

    if (!isset($expressionData['context']) || !is_array($expressionData['context'])) {
        fail('resolveExpressionOutput success missing context');
    }

    if (($expressionData['context']['character_id'] ?? null) !== $expressionTestCharacterId) {
        fail('resolveExpressionOutput success returned unexpected context.character_id');
    }

    if (!isset($expressionData['resolved_output']) || !is_array($expressionData['resolved_output'])) {
        fail('resolveExpressionOutput success missing resolved_output');
    }

    foreach (['layer_voice', 'layer_psych', 'layer_limbic'] as $layerKey) {
        if (!array_key_exists($layerKey, $expressionData['resolved_output'])) {
            fail('resolveExpressionOutput success missing ' . $layerKey);
        }

        if (!is_array($expressionData['resolved_output'][$layerKey])) {
            fail('resolveExpressionOutput success expected ' . $layerKey . ' to be an array');
        }
    }

    if (!array_key_exists('override_rules', $expressionData) || !is_array($expressionData['override_rules'])) {
        fail('resolveExpressionOutput success missing override_rules array');
    }

    if (!array_key_exists('surface_directives', $expressionData) || !is_array($expressionData['surface_directives'])) {
        fail('resolveExpressionOutput success missing surface_directives array');
    }

    assert_expression_output_matches(
        $expressionData['resolved_output'],
        $expressionExpectedDefault,
        'resolveExpressionOutput success'
    );

    /*
     * resolveExpressionOutput missing character_id
     */
    assert_expression_error_result(
        run_get_endpoint(
            $runnerPath,
            $resolveExpressionOutputScript,
            []
        ),
        'character_id must be a non-empty string',
        'resolveExpressionOutput missing character_id'
    );

    /*
     * resolveExpressionOutput wrong method
     */
    assert_expression_error_result(
        run_endpoint(
            $runnerPath,
            $resolveExpressionOutputScript,
            [],
            'ci-test-key',
            'POST',
            [
                'character_id' => $expressionTestCharacterId,
            ]
        ),
        'Method not allowed',
        'resolveExpressionOutput wrong method'
    );

    /*
     * resolveExpressionOutput invalid integer parameter
     */
    assert_expression_error_result(
        run_get_endpoint(
            $runnerPath,
            $resolveExpressionOutputScript,
            [
                'character_id' => $expressionTestCharacterId,
                'domain_id' => 'abc',
            ]
        ),
        'Invalid integer parameter',
        'resolveExpressionOutput invalid domain_id'
    );

    /*
     * resolveExpressionOutput optional context parameters round-trip
     */
    $expressionContextResult = run_get_endpoint(
        $runnerPath,
        $resolveExpressionOutputScript,
        [
            'character_id' => $expressionTestCharacterId,
            'character_entity_id' => '101',
            'interlocutor_entity_id' => '202',
            'social_context_id' => '303',
        ]
    );

    /*
 * suggestLinkEntity behaviour
 *
 * These tests rely on the fixture seeded by:
 * private/framework/ci/seed_ci_data.php
 */
    $suggestLinkEntityScript =
        $repoRoot . '/public_html/pecherie/chill-api/entity/suggest_link_entity.php';

    if (!is_file($suggestLinkEntityScript)) {
        fail('Missing entity/suggest_link_entity.php');
    }

    $entitySubjectId = $seededIds['entity_test_subject_entity_id'];
    $entityExistingTargetLabel = $seededIds['entity_test_existing_target_label'];
    $entityExistingTargetTypeId = $seededIds['entity_test_existing_target_type_id'];
    $entityFactTypeId = $seededIds['entity_test_fact_type_id'];
    $entityDuplicateTargetLabel = $seededIds['entity_test_duplicate_link_target_label'];
    $entityDuplicateTargetTypeId = $seededIds['entity_test_duplicate_link_target_type_id'];
    $entityAmbiguousTargetLabel = $seededIds['entity_test_ambiguous_target_label'];
    $entityAmbiguousTargetTypeId = $seededIds['entity_test_ambiguous_target_type_id'];
    $entitySameTypeAmbiguousLabel = $seededIds['entity_test_same_type_ambiguous_label'];
    $entitySameTypeAmbiguousTypeId = $seededIds['entity_test_same_type_ambiguous_type_id'];


    /*
     * suggestLinkEntity success path: existing entity + explicit subject
     */
    $entityExistingExplicitResult = run_endpoint(
        $runnerPath,
        $suggestLinkEntityScript,
        [
            'subject_entity_id' => $entitySubjectId,
            'raw_label' => $entityExistingTargetLabel,
            'entity_type_id' => $entityExistingTargetTypeId,
            'fact_type_id' => $entityFactTypeId,
        ]
    );

    $entityExistingExplicitJson = assert_entity_success_result(
        $entityExistingExplicitResult,
        'suggestLinkEntity existing entity explicit subject'
    );

    $entityExistingExplicitData = assert_entity_action(
        $entityExistingExplicitJson,
        'link_entity_generic',
        'suggestLinkEntity existing entity explicit subject'
    );

    if (($entityExistingExplicitData['subject_entity_id'] ?? null) !== $entitySubjectId) {
        fail('suggestLinkEntity existing entity explicit subject returned unexpected subject_entity_id');
    }

    $entityExistingExplicitStepMap = extract_entity_step_map(
        $entityExistingExplicitJson,
        'suggestLinkEntity existing entity explicit subject'
    );

    assert_step_names(
        $entityExistingExplicitStepMap,
        ['link_entity'],
        'suggestLinkEntity existing entity explicit subject'
    );

    $expectedInsertFragment = 'INSERT INTO sxnzlfun_chrysalis.entity_linked_facts';

    assert_sql_contains_fragment(
        $entityExistingExplicitStepMap['link_entity'],
        $expectedInsertFragment,
        'suggestLinkEntity existing entity explicit subject'
    );

    assert_sql_contains_fragment(
        $entityExistingExplicitStepMap['link_entity'],
        'WHERE NOT EXISTS',
        'suggestLinkEntity existing entity explicit subject'
    );

    ok('suggestLinkEntity existing entity explicit subject returned link-only SQL');

    /*
     * suggestLinkEntity success path: new entity + explicit subject
     */
    $entityNewLabel = 'CI Fresh Entity ' . gmdate('Ymd_His');

    $entityNewExplicitResult = run_endpoint(
        $runnerPath,
        $suggestLinkEntityScript,
        [
            'subject_entity_id' => $entitySubjectId,
            'raw_label' => $entityNewLabel,
            'entity_type_id' => $entityExistingTargetTypeId,
            'fact_type_id' => $entityFactTypeId,
        ]
    );

    $entityNewExplicitJson = assert_entity_success_result(
        $entityNewExplicitResult,
        'suggestLinkEntity new entity explicit subject'
    );

    $entityNewExplicitData = assert_entity_action(
        $entityNewExplicitJson,
        'create_and_link_entity_generic',
        'suggestLinkEntity new entity explicit subject'
    );

    if (($entityNewExplicitData['subject_entity_id'] ?? null) !== $entitySubjectId) {
        fail('suggestLinkEntity new entity explicit subject returned unexpected subject_entity_id');
    }

    $entityNewExplicitStepMap = extract_entity_step_map(
        $entityNewExplicitJson,
        'suggestLinkEntity new entity explicit subject'
    );

    assert_step_names(
        $entityNewExplicitStepMap,
        ['create_entity', 'create_label', 'link_entity'],
        'suggestLinkEntity new entity explicit subject'
    );

    assert_sql_contains_fragment(
        $entityNewExplicitStepMap['create_entity'],
        'INSERT INTO sxnzlfun_chrysalis.entities',
        'suggestLinkEntity new entity explicit subject'
    );

    assert_sql_contains_fragment(
        $entityNewExplicitStepMap['create_label'],
        'INSERT INTO sxnzlfun_chrysalis.entity_texts',
        'suggestLinkEntity new entity explicit subject'
    );

    assert_sql_contains_fragment(
        $entityNewExplicitStepMap['link_entity'],
        'INSERT INTO sxnzlfun_chrysalis.entity_linked_facts',
        'suggestLinkEntity new entity explicit subject'
    );

    ok('suggestLinkEntity new entity explicit subject returned create + label + link SQL');

    /*
     * suggestLinkEntity duplicate-link path remains idempotent
     */
    $entityDuplicateResult = run_endpoint(
        $runnerPath,
        $suggestLinkEntityScript,
        [
            'subject_entity_id' => $entitySubjectId,
            'raw_label' => $entityDuplicateTargetLabel,
            'entity_type_id' => $entityDuplicateTargetTypeId,
            'fact_type_id' => $entityFactTypeId,
        ]
    );

    $entityDuplicateJson = assert_entity_success_result(
        $entityDuplicateResult,
        'suggestLinkEntity duplicate link explicit subject'
    );

    $entityDuplicateData = assert_entity_action(
        $entityDuplicateJson,
        'link_entity_generic',
        'suggestLinkEntity duplicate link explicit subject'
    );

    $entityDuplicateStepMap = extract_entity_step_map(
        $entityDuplicateJson,
        'suggestLinkEntity duplicate link explicit subject'
    );

    assert_step_names(
        $entityDuplicateStepMap,
        ['link_entity'],
        'suggestLinkEntity duplicate link explicit subject'
    );

    assert_sql_contains_fragment(
        $entityDuplicateStepMap['link_entity'],
        'WHERE NOT EXISTS',
        'suggestLinkEntity duplicate link explicit subject'
    );

    ok('suggestLinkEntity duplicate link explicit subject returned idempotent link SQL');

    /*
     * suggestLinkEntity failure: missing explicit subject
     */
    $entityMissingSubjectJson = assert_entity_error_result(
        run_endpoint(
            $runnerPath,
            $suggestLinkEntityScript,
            [
                'raw_label' => $entityExistingTargetLabel,
                'entity_type_id' => $entityExistingTargetTypeId,
                'fact_type_id' => $entityFactTypeId,
            ]
        ),
        'subject_entity_id must be a non-empty string',
        'suggestLinkEntity missing explicit subject'
    );

    assert_no_entity_sql_steps(
        $entityMissingSubjectJson,
        'suggestLinkEntity missing explicit subject'
    );

    /*
     * Canonical label resolution rules:
     *
     * - Resolution is scoped by entity_type_id.
     * - Cross-type duplicate labels are valid when entity_type_id is provided.
     * - Ambiguity exists only when multiple entities of the same type share the same canonical label.
     */

    /*
     * suggestLinkEntity failure: ambiguous canonical label within type
     */
    $entitySameTypeAmbiguousJson = assert_entity_error_result(
        run_endpoint(
            $runnerPath,
            $suggestLinkEntityScript,
            [
                'subject_entity_id' => $entitySubjectId,
                'raw_label' => $entitySameTypeAmbiguousLabel,
                'entity_type_id' => $entitySameTypeAmbiguousTypeId,
                'fact_type_id' => $entityFactTypeId,
            ]
        ),
        'Ambiguous canonical label match',
        'suggestLinkEntity ambiguous canonical label within type'
    );

    assert_no_entity_sql_steps(
        $entitySameTypeAmbiguousJson,
        'suggestLinkEntity ambiguous canonical label within type'
    );

    /*
     * suggestLinkEntity failure: missing fact_type_id
     */
    $entityMissingFactTypeJson = assert_entity_error_result(
        run_endpoint(
            $runnerPath,
            $suggestLinkEntityScript,
            [
                'subject_entity_id' => $entitySubjectId,
                'raw_label' => $entityExistingTargetLabel,
                'entity_type_id' => $entityExistingTargetTypeId,
            ]
        ),
        'fact_type_id must be a non-empty string',
        'suggestLinkEntity missing fact_type_id'
    );

    assert_no_entity_sql_steps(
        $entityMissingFactTypeJson,
        'suggestLinkEntity missing fact_type_id'
    );


    /*
 * suggestLinkEntity success: cross-type duplicate label resolved by type
 */
    $entityCrossTypeJson = assert_entity_success_result(
        run_endpoint(
            $runnerPath,
            $suggestLinkEntityScript,
            [
                'subject_entity_id' => $entitySubjectId,
                'raw_label' => $entityAmbiguousTargetLabel,
                'entity_type_id' => $entityAmbiguousTargetTypeId,
                'fact_type_id' => $entityFactTypeId,
            ]
        ),
        'suggestLinkEntity resolves cross-type duplicate when type specified'
    );

    $entityCrossTypeData = assert_entity_action(
        $entityCrossTypeJson,
        'link_entity_generic',
        'suggestLinkEntity resolves cross-type duplicate when type specified'
    );

    $entityCrossTypeStepMap = extract_entity_step_map(
        $entityCrossTypeJson,
        'suggestLinkEntity resolves cross-type duplicate when type specified'
    );

    assert_step_names(
        $entityCrossTypeStepMap,
        ['link_entity'],
        'suggestLinkEntity resolves cross-type duplicate when type specified'
    );

    assert_sql_contains_fragment(
        $entityCrossTypeStepMap['link_entity'],
        'INSERT INTO sxnzlfun_chrysalis.entity_linked_facts',
        'suggestLinkEntity resolves cross-type duplicate when type specified'
    );

    ok('suggestLinkEntity cross-type duplicate resolved correctly');


    $expressionContextJson = assert_expression_success_result(
        $expressionContextResult,
        'resolveExpressionOutput context round-trip'
    );

    $expressionContext = $expressionContextJson['data']['context'] ?? null;
    if (!is_array($expressionContext)) {
        fail('resolveExpressionOutput context round-trip missing context');
    }

    if (($expressionContext['character_entity_id'] ?? null) !== 101) {
        fail('resolveExpressionOutput context round-trip returned unexpected character_entity_id');
    }

    if (($expressionContext['interlocutor_entity_id'] ?? null) !== 202) {
        fail('resolveExpressionOutput context round-trip returned unexpected interlocutor_entity_id');
    }

    if (($expressionContext['social_context_id'] ?? null) !== 303) {
        fail('resolveExpressionOutput context round-trip returned unexpected social_context_id');
    }

    ok('resolveExpressionOutput context round-trip works');

    /*
     * resolveExpressionOutput domain filtering
     */
    $expressionDomainResult = run_get_endpoint(
        $runnerPath,
        $resolveExpressionOutputScript,
        [
            'character_id' => $expressionTestCharacterId,
            'domain_id' => (string) $expressionDomainMatchId,
        ]
    );

    $expressionDomainJson = assert_expression_success_result(
        $expressionDomainResult,
        'resolveExpressionOutput with domain'
    );

    $expressionDomainContext = $expressionDomainJson['data']['context'] ?? null;
    if (!is_array($expressionDomainContext)) {
        fail('resolveExpressionOutput with domain missing context');
    }

    if (($expressionDomainContext['domain_id'] ?? null) !== $expressionDomainMatchId) {
        fail('resolveExpressionOutput with domain returned unexpected context.domain_id');
    }

    assert_expression_output_matches(
        $expressionDomainJson['data']['resolved_output'],
        $expressionExpectedDomainFiltered,
        'resolveExpressionOutput with domain'
    );

    ok('Expression output API behaviour tests passed');
    ok('Repo API behaviour tests passed');
} finally {
    delete_file_if_present($runnerPath);
}