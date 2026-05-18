<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$pdo = db();

$first = fw_start_workflow(
    $pdo,
    'calendar_event_add_prose'
);

echo "\n================ START WORKFLOW ================\n\n";

print_r($first);

/*
|--------------------------------------------------------------------------
| Validate awaiting_input semantics
|--------------------------------------------------------------------------
*/

if (($first['status'] ?? null) !== 'awaiting_input') {

    throw new RuntimeException(
        'Expected workflow to await input.'
    );
}

if (($first['expected_input'] ?? null) !== 'entity_id') {

    throw new RuntimeException(
        'Expected workflow to await entity_id.'
    );
}

/*
|--------------------------------------------------------------------------
| Resume workflow with natural user reply
|--------------------------------------------------------------------------
*/

$resumed = fw_resume_workflow(
    $pdo,
    'calendar_event_add_prose',
    $first['state_id'],
    [
        'entity_id' => 'calendar_event:7',
    ],
    $first['context'] ?? [],
    $first['snapshots'] ?? []
);

echo "\n================ RESUME WORKFLOW ================\n\n";

print_r($resumed);