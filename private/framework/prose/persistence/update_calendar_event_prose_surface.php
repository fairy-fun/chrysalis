/* --------------------------------------------------------------------------
Populate lightweight semantic discoverability surface
-----------------------------------------------------------------------------

calendar_events.prose_body is NOT canonical prose storage.

This is ONLY a lightweight semantic linkage surface.

----------------------------------------------------------------------------- */

$proseBody = 'prose_family:' . $proseFamilyId;

ensure_calendar_node(
    $pdo,
    [
        'id' => $calendarEventId,
        'prose_body' => $proseBody,
    ]
);