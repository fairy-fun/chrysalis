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

$pdo->beginTransaction();

try {

    /* ------------------------------------------------------------------
    Resolve or create prose family
    ------------------------------------------------------------------ */

    $proseFamilyId = resolve_or_create_prose_family(
        $pdo,
        $calendarEventId
    );

    /* ------------------------------------------------------------------
    Persist canonical topology
    ------------------------------------------------------------------ */

    $passageStmt = $pdo->prepare(
        '
        INSERT INTO calendar_event_passages (
            calendar_event_id,
            prose_family_id
        )
        VALUES (
            :calendar_event_id,
            :prose_family_id
        )
        ON DUPLICATE KEY UPDATE
            prose_family_id = VALUES(prose_family_id)
        '
    );

    $passageStmt->execute([
        ':calendar_event_id' => $calendarEventId,
        ':prose_family_id' => $proseFamilyId,
    ]);

    /* ------------------------------------------------------------------
    Populate lightweight semantic discoverability surface
    ------------------------------------------------------------------ */

    $proseBody = 'prose_family:' . $proseFamilyId;

    $eventStmt = $pdo->prepare(
        '
        UPDATE calendar_events
        SET prose_body = :prose_body
        WHERE id = :calendar_event_id
        '
    );

    $eventStmt->execute([
        ':prose_body' => $proseBody,
        ':calendar_event_id' => $calendarEventId,
    ]);

    $pdo->commit();

} catch (\Throwable $e) {

    $pdo->rollBack();

    throw $e;
}