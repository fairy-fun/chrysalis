<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../private/framework/calendar/calendar_prose_batch_planner.php';

$parentEventEntityId = (string)($input['parent_event_entity_id'] ?? '');
$prose = (string)($input['prose'] ?? '');

$result = generate_calendar_batch_from_prose(
    $parentEventEntityId,
    $prose
);

echo json_encode($result, JSON_PRETTY_PRINT);
exit;