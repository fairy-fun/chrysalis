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