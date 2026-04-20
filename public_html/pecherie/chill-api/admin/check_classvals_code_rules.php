<?php
declare(strict_types=1);

function require_or_fail(string $path, string $label): void
{
    if (!is_file($path)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
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
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(
            ['status' => 'error', 'error' => 'method not allowed'],
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        exit;
    }

    // enforce_api_key('admin');

    $db = db();
    $hardStrict = !isset($_GET['hard_strict']) || $_GET['hard_strict'] !== '0';
    $expectedDefiner = (string)$db->query('SELECT CURRENT_USER()')->fetchColumn();

    $singleTable = isset($_GET['table']) ? trim((string)$_GET['table']) : null;
    $eligibleTables = list_classvals_tables($db);

    if ($singleTable !== null && $singleTable !== '') {
        if (!in_array($singleTable, $eligibleTables, true)) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'read_only' => true,
                'writes_performed' => false,
                'error' => 'table is not an eligible *_classvals table with a code column',
                'table' => $singleTable,
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        }

        $tables = [$singleTable];
    } else {
        $tables = $eligibleTables;
    }

    $results = [];
    $okCount = 0;
    $badCount = 0;

    foreach ($tables as $table) {
        $inspection = inspect_classval_trigger_state(
            $db,
            (string)$table,
            $hardStrict,
            $expectedDefiner
        );

        $results[] = $inspection;

        if (!empty($inspection['ok'])) {
            $okCount++;
        } else {
            $badCount++;
        }
    }

    $status = $badCount === 0 ? 'ok' : 'drift';
    http_response_code($badCount === 0 ? 200 : 409);

    echo json_encode([
        'status' => $status,
        'read_only' => true,
        'writes_performed' => false,
        'summary' => [
            'eligible_tables' => count($eligibleTables),
            'checked' => count($results),
            'ok' => $okCount,
            'drifted' => $badCount,
            'hard_strict' => $hardStrict,
            'expected_definer' => $expectedDefiner,
        ],
        'tables' => $results,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'read_only' => true,
        'writes_performed' => false,
        'error' => $e->getMessage(),
        'type' => get_class($e),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}