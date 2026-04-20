<?php
declare(strict_types=1);

function require_or_fail(string $path, string $label): void
{
    if (!is_file($path)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'dry_run' => true,
            'writes_performed' => false,
            'missing' => $label,
            'checked_path' => $path,
            'script_dir' => __DIR__,
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    require_once $path;
}

require_or_fail(dirname(__DIR__, 4) . '/private/bootstrap.php', 'private/bootstrap.php');
require_or_fail(
    dirname(__DIR__, 4) . '/private/infrastructure/classvals/trigger_admin.php',
    'private/infrastructure/classvals/trigger_admin.php'
);

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(
            [
                'status' => 'error',
                'dry_run' => true,
                'writes_performed' => false,
                'error' => 'method not allowed',
            ],
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        exit;
    }

    // enforce_api_key('admin');

    $db = db();
    $hardStrict = !isset($_GET['hard_strict']) || $_GET['hard_strict'] !== '0';
    $expectedDefiner = current_account($db);

    $singleTable = isset($_GET['table']) ? trim((string) $_GET['table']) : null;

    $tables = ($singleTable !== null && $singleTable !== '')
        ? [$singleTable]
        : list_classvals_tables($db);

    $results = [];
    $planned = 0;
    $alreadyOk = 0;

    foreach ($tables as $table) {
        $table = (string)$table;

        $inspection = inspect_classval_trigger_state(
            $db,
            $table,
            $hardStrict,
            $expectedDefiner
        );

        $isOk = !empty($inspection['ok']);

        $sqlPlan = [];
        if (!$isOk) {
            $specs = canonical_trigger_specs($table, $expectedDefiner);
            $names = canonical_trigger_names($table);

            $sqlPlan = [
                'DROP TRIGGER IF EXISTS ' . qident((string)$names['normalize']),
                trim((string)$specs[$names['normalize']]['sql']),
                'DROP TRIGGER IF EXISTS ' . qident((string)$names['immutable']),
                trim((string)$specs[$names['immutable']]['sql']),
            ];
        }

        if ($isOk) {
            $alreadyOk++;
        } else {
            $planned++;
        }

        $results[] = [
            'table' => $table,
            'ok' => $isOk,
            'status' => $inspection['status'] ?? ($isOk ? 'ok' : 'drift'),
            'inspection' => $inspection,
            'plan_required' => !$isOk,
            'sql' => $sqlPlan,
        ];
    }

    http_response_code(200);

    echo json_encode([
        'status' => 'plan',
        'dry_run' => true,
        'writes_performed' => false,
        'summary' => [
            'eligible_tables' => count($tables),
            'planned_repairs' => $planned,
            'already_ok' => $alreadyOk,
            'hard_strict' => $hardStrict,
            'expected_definer' => $expectedDefiner,
            'writes_performed' => false,
        ],
        'tables' => $results,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'dry_run' => true,
        'writes_performed' => false,
        'error' => $e->getMessage(),
        'type' => get_class($e),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}