# =========================================
# private/docs/prose/create_prose_draft_json_contract.md
# =========================================

## Postman Request — Create Prose Draft

**Method**
POST

**URL**
https://antheapeche.com/pecherie/chill-api/index.php

**Headers**
Content-Type: application/json  
X-API-Key: <your key>

**Body (raw JSON)**

```json
{
  "operation": "createProseDraft",
  "entity_id": "prose_draft:BOOK-001-W3-D1-T1-v1",
  "title": "Book 1 — Week 3 Sunday Early Morning",
  "prose_body": "Line 1\nLine 2\nLine 3",
  "draft_status_id": "prose_status_draft",
  "author_entity_id": null,
  "projection": {
    "projection_type_id": "projection_type_book",
    "target_entity_id": "calendar_event:322",
    "role_id": "primary",
    "projection_order": 1,
    "is_export_target": 1
  },
  "annotations": []
}

```

Purpose

Creates a prose draft that can later be converted into calendar subevents.

This endpoint does not create calendar nodes.

Behavior
Stores prose as canonical input
Associates prose with a target calendar event via projection
Does NOT:
call planner
generate plan_id
assign client_id
write to calendar_events
Critical Rules
1. JSON Only
   Use raw JSON
   Do not use multipart unless uploading files
2. prose_body
   Use \n for line breaks
   Each line becomes a candidate subevent (beat)
3. draft_status_id

Must exist in:

entities.id WHERE entity_type_id = 'entity_type_status'
4. projection.target_entity_id

Must be a valid:

calendar_event:<id>

This is the parent event for future batch execution.

5. annotations

Must always be an array:

"annotations": []
Next Step (REQUIRED)

To generate calendar subevents:

POST /pecherie/chill-api/index.php

{
"operation": "executeCalendarBatchFromProse",
"parent_event_entity_id": "calendar_event:322",
"prose": "Line 1\nLine 2\nLine 3"
}
Execution Guarantees (Batch)
Deterministic: same prose → same plan_id
Idempotent: safe retries
Replay-safe: no duplicate subevents
Parallel-safe: DB-enforced uniqueness