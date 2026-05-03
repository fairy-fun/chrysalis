<?php

declare(strict_types=1);

/**
 * @deprecated Use calendar_event_semantic_creator.php instead.
 * This legacy structural creator bypasses semantic parent validation.
 */

function create_calendar_event(...$args): array
{
    throw new RuntimeException(
        'create_calendar_event is deprecated. Use create_calendar_event_under_time_entity instead.'
    );
}