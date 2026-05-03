<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/prose/prose_calendar_target_resolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$pdo = makePdo('read');
verifyExpectedDatabase($pdo);

$databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();

if (!is_string($databaseName) || $databaseName === '') {
    respond(500, [
        'status' => 'error',
        'error' => 'Unable to determine active database'
    ]);
}

// --- Inputs ---
$proseEntityId = $_GET['prose_entity_id'] ?? null;
$projectionTypeId = $_GET['projection_type_id'] ?? null;
$roleId = $_GET['role_id'] ?? null;
$requireExportTarget = ($_GET['require_export_target'] ?? '1') === '1';

// --- Validate ---
if (!is_string($proseEntityId) || trim($proseEntityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'prose_entity_id is required'
    ]);
}

try {
    $target = resolve_prose_target_calendar_node(
        $pdo,
        trim($proseEntityId),
        is_string($projectionTypeId) && trim($projectionTypeId) !== '' ? trim($projectionTypeId) : null,
        is_string($roleId) && trim($roleId) !== '' ? trim($roleId) : null,
        $requireExportTarget
    );

    if ($target === null) {
        respond(200, [
            'status' => 'ok',
            'database' => $databaseName,
            'target' => null
        ]);
    }

    // --- Derived (NON-PERSISTED) suggestion capability ---
    if ($target['layer_id'] === 'calendar_layer_time') {
        $canSuggest = 'events';
    } elseif ($target['layer_id'] === 'calendar_layer_event') {
        $canSuggest = 'subevents';
    } else {
        $canSuggest = 'none';
    }

    $target['can_suggest'] = $canSuggest;

    respond(200, [
        'status' => 'ok',
        'database' => $databaseName,
        'target' => $target
    ]);

} catch (Throwable $e) {
    respond(500, [
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
