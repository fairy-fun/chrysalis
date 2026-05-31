<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/calendar/calendar_event_metadata_applier.php';

/* --------------------------------------------------------------------------
SEMANTIC DISCOVERABILITY DOCTRINE
-----------------------------------------------------------------------------

calendar_events.prose_body is NOT canonical prose storage.

Canonical prose topology lives in:
- calendar_event_passages
- prose_families
- prose_drafts

prose_body exists ONLY as a lightweight denormalized semantic linkage
surface to assist:
- discoverability
- lightweight semantic routing
- workflow lookup
- legacy compatibility
- shallow prose attachment inspection

Expected format:

    prose_family:<id>

Example:

    prose_family:13

Do NOT store canonical prose content here.

----------------------------------------------------------------------------- */

/**
 * Synchronize lightweight prose discoverability linkage onto the
 * calendar event semantic surface.
 */
function update_calendar_event_prose_surface(
    PDO $pdo,
    int $calendarEventId,
    int $proseFamilyId
): void {

    if ($calendarEventId <= 0) {
        throw new InvalidArgumentException(
            'Invalid calendar event id.'
        );
    }

    if ($proseFamilyId <= 0) {
        throw new InvalidArgumentException(
            'Invalid prose family id.'
        );
    }

    $semanticSurface = build_calendar_event_prose_surface(
        $proseFamilyId
    );

    apply_calendar_event_prose_surface(
        $pdo,
        $calendarEventId,
        $semanticSurface
    );
}

/**
 * Build lightweight semantic discoverability linkage surface.
 */
function build_calendar_event_prose_surface(
    int $proseFamilyId
): string {

    return 'prose_family:' . $proseFamilyId;
}
