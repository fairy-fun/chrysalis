<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_layer_ensurers.php';

/**
 * @deprecated Use ensure_calendar_event(...). Kept as a compatibility shim only.
 */
function create_calendar_event_under_time_entity(
    PDO $pdo,
    string $timeEntityId,
    array $payload
): array {
    return ensure_calendar_event(
        $pdo,
        $timeEntityId,
        null,
        $payload
    );
}

/**
 * @deprecated Use ensure_calendar_event(...). Kept as a compatibility shim only.
 */
function create_calendar_event_under_event_entity(
    PDO $pdo,
    string $parentEventEntityId,
    array $payload
): array {
    return ensure_calendar_event(
        $pdo,
        $parentEventEntityId,
        null,
        $payload
    );
}
