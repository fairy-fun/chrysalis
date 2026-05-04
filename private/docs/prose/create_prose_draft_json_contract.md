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

## Purpose

Creates a prose draft used as input for deterministic calendar subevent generation.

This endpoint does not create calendar events or subevents.

## Behavior
Stores prose as canonical input
Associates prose with a target calendar event
Does NOT:
call planner
generate plan_id
assign client_id
write to calendar_events
## Critical Rules
1. JSON Only
Use raw JSON
Do not use multipart unless uploading files
2. prose_body
Use \n for line breaks
Each non-empty line represents exactly one subevent (beat)
Line order is preserved and used for deterministic execution
3. draft_status_id

Must exist in:

entities.id WHERE entity_type_id = 'entity_type_status'
4. projection.target_entity_id

Format:

calendar_event:<id>

Rules:

MUST refer to a calendar_layer_event node
Subevents attach only to event nodes
MUST match the parent_event_entity_id used during batch execution
5. annotations

Must always be an array:

"annotations": []
Next Step (REQUIRED)

Execute batch to create subevents:

POST /pecherie/chill-api/index.php

{
  "operation": "executeCalendarBatchFromProse",
  "parent_event_entity_id": "calendar_event:322",
  "prose": "Line 1\nLine 2\nLine 3"
}
Execution Input Rule
executeCalendarBatchFromProse uses the provided "prose" string.
It does NOT read from stored prose_draft records.
Execution Guarantees
Deterministic: same prose → same plan_id
Idempotent: safe retries
Replay-safe: no duplicate subevents
Parallel-safe: enforced by DB uniqueness